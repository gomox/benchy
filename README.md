Benchy 
======

A simple apachebench visualization tool written in PHP.

ApacheBench is handy, but it makes it really hard to perform any kind
of systematic performance analysis. Its output format also sucks for
visualization. Benchy tries to make this simpler using stuff most web
developers have handy: a web server, a web browser, a CLI PHP interpreter and an
Internet connection (because I felt like loading jQuery and Highcharts
from a CDN instead of copying them into this project - send me a PR :P).

This is a quick and dirty project but should be pretty handy nonetheless.
Hopefully you will be able to defer using JMeter for a while longer with this. It's also prettier than JMeter.

![Screenshot](raw.github.com/gomox/benchy/master/screenshots/benchy_multiple.png)

How to install Benchy
---------------------

Dependencies (doh):

 * `php`
 * `apachebench`
 * a web server (`python -m SimpleHTTPServer` will do!)
 * a web browser

Installation steps:

 * Just copy the Benchy folder somewhere within your `DOCUMENT_ROOT` or equivalent (see Note below on why the webserver is necessary)
 * If you have a working webserver **with PHP**, put Benchy within it if you
   can. Either way, see the usage instructions below.


How to use Benchy
-----------------

1. Copy `config.json.template` into `config.json`
2. Edit `config.json` to set up your testing preferences (usually the URL at least)
3. Run `php benchy.php`
4. Depending on your case do one of the following:

    *  If you have a working webserver that supports PHP, you can access index.php directly through it.
    * If you don't, just do `php index.php > index.html` and access `index.html` instead.


Full example
------------

Say I have a webpage at `http://192.168.100.100/mypage/` that is making my production
servers weep. I will try and optimize it, but in order to measure the impact of the
changes I make, I will use Benchy to keep track of what I'm doing.

I will go into my Benchy folder and wipe whatever is in the `data`  subdirectory to
start fresh. Next, I will edit config.json and set the URL to `http://192.168.100.100/mypage/`

Benchy does a ramp up of concurrency so that you can see the performance trend instead
of just an isolated data point. Typically Benchy starts with a single concurrent request
and doubles the concurrency all the way up to 128 simultaneous requests. Feel free to adjust
this stepping to your needs in config.json.

For this example, I will stick to the default `tests` setting (1, 2, 4 ... 128).

Next, I will run Benchy:

    $ php benchy.php

Benchy will prompt me for a comment that will later help me identify this "run". Because
I haven't done anything yet, I just type in `baseline` as a comment.

Now the fun part - wait until the benchmarking is over. If your code doesn't suck,
is should finish in less than a minute with the default settings. Otherwise, just
adjust the `tests` setting in config.json to something that is workable. If your
benchmark run takes 30 minutes then you are doing it wrong.

Now, assuming I don't have PHP set up on my server, I will create a Benchy report
to check the results:

    $ php index.php > index.html

I can now fire up my web browser and go find that very `index.html`, it should look
like this:

![Screenshot](raw.github.com/gomox/benchy/master/screenshots/benchy_single.png)

Now, I will tweak my code and configuration to see if I can squeeze more performance
out of that stupid `mypage` site. Say I notice the code is using `Zend_Json::encode()` instead
of the native `json_encode()` which is written in C. I guess I can improve things by
replacing that. So I do it, and then:

    $ php benchy.php

When prompted for a comment, I will type in `with json_encode()`

Next, I'll tweak the PHP settings on the server to enable APC. That should make
things faster. Again:

    $ php benchy.php

When prompted for a comment, I will type in `with json_encode() and APC`

I will now regenerate the report to see if I have achieved something:
    
    $ php index.php > index.html


![Screenshot](raw.github.com/gomox/benchy/master/screenshots/benchy_multiple.png)

Tada :) Just click on the checkboxes to compare the datasets you have, or
look at different metrics. Enjoy!



Notes
-----

The webserver is necessary just because your browser will complain about same-origin policies
when trying to load the `.dat` files to plot the graphs. If you know a way of dodging the
web server and using `file://` URLs, let me know! Sorry :(

Kudos
-----

Benchy was made possible by the cool guys behing apachebench, jQuery and Highcharts.


