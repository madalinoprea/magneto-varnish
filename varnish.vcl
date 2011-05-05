# This is a basic VCL configuration file for varnish.  See the vcl(7)
# man page for details on VCL syntax and semantics.
# 
# Default backend definition.  Set this to point to your content
# server.
# 
 backend default {
     .host = "127.0.0.1";
     .port = "81"; # READ THIS: You should configure Apache to run on port 81
     .max_connections = 30;
 }

acl trusted {
    "127.0.0.1";
    # Add other ips that are allowed to purge cache
}

# 
# http://www.varnish-cache.org/docs/2.1/tutorial/vcl.html#vcl-recv
# @param req    Request object
sub vcl_recv {
    if (req.http.x-forwarded-for) {
        set req.http.X-Forwarded-For = req.http.X-Forwarded-For ", " client.ip;
    }
    else {
        set req.http.X-Forwarded-For = client.ip;
    }
    
    if (req.request == "PURGE") {
        # Allow requests from trusted IPs to purge the cache
        if (!client.ip ~ trusted) {
           error 405 "Not allowed.";
        }
        return(lookup); # @see vcl_hit
    }
    
    if (req.request != "GET" &&
       req.request != "HEAD" &&
       req.request != "PUT" &&
       req.request != "POST" &&
       req.request != "TRACE" &&
       req.request != "OPTIONS" &&
       req.request != "DELETE") {
         /* Non-RFC2616 or CONNECT which is weird. */
         return (pipe);
    }

     # Cache only GET or HEAD requests
     if (req.request != "GET" && req.request != "HEAD") {
         /* We only deal with GET and HEAD by default */
         return (pass);
     }

    # parse accept encoding rulesets to normalize0
    if (req.http.Accept-Encoding) {
        if (req.http.Accept-Encoding ~ "gzip") {
            set req.http.Accept-Encoding = "gzip";
        } elsif (req.http.Accept-Encoding ~ "deflate") {
            set req.http.Accept-Encoding = "deflate";
        } else {
            # unkown algorithm
            remove req.http.Accept-Encoding;
        }
    }

     # Rules for static files
     if (req.url ~ "\.(jpeg|jpg|png|gif|ico|swf|js|css|gz|rar|txt|bzip|pdf)(\?.*|)$") {
        set req.http.staticmarker = "1";
        unset req.http.Cookie;

        return (lookup);
    }

    # Don't cache pages for Magento Admin
    # FIXME: change this rule if you use custom url in admin
    if (req.url ~ "^/admin" || req.url ~ "^/index.php/admin") {
        return(pass);
    }

    # Don't cache checkout/customer pages
    if (req.url ~ "^/checkout" || req.url ~ "^/customer") {
        return(pass);
    }

    # Don't cache till session end
    if (req.http.cookie ~ "nocache_stable") {
        return(pass);
    }

    # Unique identifier witch tell Varnish use cache or not
    if (req.http.cookie ~ "nocache") {
        return(pass);
    }

    # Remove cookie 
    unset req.http.Cookie;
    set req.http.magicmarker = "1"; #Instruct varnish to remove cache headers received from backend
    return(lookup);
 }


sub vcl_pipe {
#     # Note that only the first request to the backend will have
#     # X-Forwarded-For set.  If you use X-Forwarded-For and want to
#     # have it set for all requests, make sure to have:
#     # set req.http.connection = "close";
#     # here.  It is not set by default as it might break some broken web
#     # applications, like IIS with NTLM authentication.
     return (pipe);
}
 
#sub vcl_pass {
#     return (pass);
#}
 
#sub vcl_hash {
#     set req.hash += req.url;
#     if (req.http.host) {
#         set req.hash += req.http.host;
#     } else {
#         set req.hash += server.ip;
#     }
#     return (hash);
# }


# Called after a cache lookup if the req. document was found in the cache.
sub vcl_hit {
    if (req.request == "PURGE") {
        purge_url(req.url);
        error 200 "Purged";
    }
    
    if (!obj.cacheable) {
        return (pass);
    }
    return (deliver);
}

# Called after a cache lookup and odc was not found in cache.
sub vcl_miss {
    if (req.request == "PURGE"){
        error 200 "Not in cache";
    }
    return (fetch);
}

# Called after document was retreived from backend
# @var req      Request object.
# @var beresp   Backend response (contains HTTP headers from backend)
sub vcl_fetch {
    set req.grace = 30s;

    # Flag set when we want to delete cache headers received from backend
    if (req.http.magicmarker){
        unset beresp.http.magicmarker;
        unset beresp.http.Cache-Control;
        unset beresp.http.Expires;
        unset beresp.http.Pragma;
        unset beresp.http.Cache;
        unset beresp.http.Server;
        unset beresp.http.Set-Cookie;
        unset beresp.http.Age;
        
        # default ttl for pages
        set beresp.ttl = 1d;
    }
    if (req.http.staticmarker) {
        set beresp.ttl = 30d; # static file cache expires in 30 days
        unset beresp.http.staticmarker;
        unset beresp.http.ETag; # Removes Etag in case we have multiple frontends
    }

    return (deliver);
}

# Called after a cached document is delivered to the client.
sub vcl_deliver {
    if (obj.hits > 0) {
        set resp.http.X-Cache = "HIT ("obj.hits")";
    } else {
        set resp.http.X-Cache = "MISS";
        #    set resp.http.X-Cache-Hash = obj.http.hash;
    }
    return (deliver);
}
# 
# sub vcl_error {
#     set obj.http.Content-Type = "text/html; charset=utf-8";
#     synthetic {"
# <?xml version="1.0" encoding="utf-8"?>
# <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
#  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
# <html>
#   <head>
#     <title>"} obj.status " " obj.response {"</title>
#   </head>
#   <body>
#     <h1>Error "} obj.status " " obj.response {"</h1>
#     <p>"} obj.response {"</p>
#     <h3>Guru Meditation:</h3>
#     <p>XID: "} req.xid {"</p>
#     <hr>
#     <address>
#        <a href="http://www.varnish-cache.org/">Varnish cache server</a>
#     </address>
#   </body>
# </html>
# "};
#     return (deliver);
# }
