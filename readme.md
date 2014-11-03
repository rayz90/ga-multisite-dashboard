Google Analytics multi-site dashboard
=========

This is a dashboard that shows the visitors of multiple different websites using the Google Analytics API. For each websites it shows a graph of the vistors in the last 24 hours and the realtime vistors.

The idea and layout is shamelessly ripped from https://github.com/Code4SA/dashboard

Screenshot
----
![A nice screenshot with some mock data](http://i.imgur.com/OJuu4ao.png)
_A nice screenshot with some mock data_

Installation
-----
1. Clone the project.
2. Edit your Apache config, so the Document Root points to the docroot folder of this project. For other webservers, [check this](http://silex.sensiolabs.org/doc/web_servers.html).
3. Run `composer install`.
4. Copy your Google p12 key to `config/key.p12` (for more info check below).
5. Add your service account, websites and dispay preferences to `config/config.json`.
6. Enjoy your dashboard.

Google Analytics OAuth2 Key
--------
All the information regarding the OAuth2 key can also be found [here](https://developers.google.com/accounts/docs/OAuth2ServiceAccount).

1. Go to the Google Developers Console.
2. Select a project, or create a new one.
3. In the sidebar on the left, expand APIs & auth. Next, click APIs. In the list of APIs, make sure the Analytics API shows a status of ON.
4. In the sidebar on the left, select Credentials.
5. To set up a new service account, do the following:
    - Under the OAuth heading, select Create new Client ID.
    - When prompted, select Service Account and click Create Client ID.
    - A dialog box appears. To proceed, click Okay, got it.

    If you already have a service account, you can generate a new key by clicking the appropriate button beneath the existing service-account credentials table.
6. Download the P12 key.
7. Now go to the Admin page of Google Analytics.
8. Add the service account as user to all the GA accounts you are using for the dashboard.

Contributing
----
Front-end stuff is not really my cup of tea. Basically, my implemention of AJAX is ye olde meta-refresh. So, feel free to improve the JS/CSS/HTML to your liking, I will gladly review your pull request. Although, I would appreciate it if you keep the general look-and-feel intact.

I think the back-end (the index.php file) is pretty solid, but there is always room for improvement. So, don't hesitate to send me a pull request for this as well.

License
----
Copyright (c) 2014 Raymond Vermaas


Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
