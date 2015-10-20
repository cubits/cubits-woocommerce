cubits-woocommerce
====================

Accept Bitcoin on your WooCommerce-powered website with cubits.

## Installation

First generate an API key with the 'user' and 'merchant' permissions at https://cubits.com/settings/api. If you don't have a cubits account, sign up at https://cubits.com/merchant.

To install the plugin:

1. Download the plugin as a .zip file from the merchant section.
2  Initialize submodule (look above for details)
3. In your WordPress administration console, navigate to Plugins > Add New > Upload.
4. Upload the .zip file downloaded in step 1.
5. Click 'Install Now' and then 'Activate Plugin.'
6. Navigate to WooCommerce > Settings, and then click on the Checkout tab at the top of the screen.
7. Click on cubits.
8. Enter your API Credentials and click on 'Save changes'.

NOTE: Do not set the callback and redirect URLs manually on cubits.com as this will interfere with the operation of the plugin.

# Cubits-PHP submodule
-------

from the the root of the plugin

1. git submodule init

2. git submodule update

# License

The MIT License (MIT)

Copyright (c) 2015 Dooga Ltd.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
