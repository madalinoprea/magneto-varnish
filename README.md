## Under Development

Implements ideas posted on [http://moprea.ro/2011/feb/16/magento-performance-optimization-varnish-cache-2/](http://moprea.ro/2011/feb/16/magento-performance-optimization-varnish-cache-2/) and 
[http://www.kalenyuk.com.ua/magento-performance-optimization-with-varnish-cache-47.html](http://www.kalenyuk.com.ua/magento-performance-optimization-with-varnish-cache-47.html).

## Features

1. Enable full page caching using [Varnish](http://www.varnish-cache.org/), a super fast caching HTTP reverse proxy.
1. Varnish servers are configurable in Admin, under System / Configuration / General - Varnish Options
1. Automatically clears (only) cached pages when products, categories and CMS pages are saved.
1. Adds a new cache type in Magento Admin, under System / Cache Management and offers the possibility to deactivate the cache
and refresh the cache.
1. Notifies Admin users when a category navigation is saved and Varnish cache needs to be refreshed so that the menu will
be updated for all pages.
1. Turns off varnish cache automatically for users that have products in the cart or are logged in, etc.)
1. Default varnish configuration offered so that the module is workable.

Screen shots: [https://github.com/madalinoprea/magneto-varnish/wiki/Screenshots](https://github.com/madalinoprea/magneto-varnish/wiki/Screenshots)


## Instalation instructions

1. Make sure you have modman installed:
<pre><code>
curl http://module-manager.googlecode.com/files/modman-1.1.5 > modman
chmod +x modman
sudo mv modman /usr/bin
</pre></code>

1. Clone the git repository:
<pre><code>
cd [magento root folder]
modman init
modman magneto-varnish clone https://github.com/madalinoprea/magneto-varnish.git
</code></pre>

In case you get an error that git is not found, you'll have to install git and rerun the last command. This can be done like this on Ubuntu:
`sudo aptitude install git-core`

1. Flush Magento cache to enable the extension

## Requirements

1. Apache started and listening on port 81 (Varnish configuration is using this port)
2. Varnish installed and listening on port 80; please use the config from repo 
3. Varnish servers configured in admin


