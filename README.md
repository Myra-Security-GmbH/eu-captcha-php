# What is the Myra EU CAPTCHA

The Myra EU CAPTCHA protects your website(s) and API(s) against security issues like bots, prevents fraud, and secures your forms from spam and credential stuffing. As a stand-alone solution from Myra, the captcha can be easily integrated with three steps into all your websites. As soon as a visitor fills out a form and submits it, the EU CAPTCHA uses so-called challenges to check whether the request is legitimate or needs to be blocked. The visitor of your website does not need to do anything, as the check runs automatically in the background. Only a Myra logo gives the visitor feedback that they passed the challenge.  

## Installation

Install the library with composer.

```sh
composer require myra-security-gmbh/eu-captcha
```

## Integration
Proceed as follows to integrate the EU CAPTCHA to your Website:
1. Register a new Account under https://app.eu-captcha.eu/user-registration. 
2. Create a new site key for your website, see https://docs.eu-captcha.eu/Content/first_steps/Captcha_create_side_key.htm.
3. Add the code for the EU Captcha to the website where your form is, see https://docs.eu-captcha.eu/Content/first_steps/Captcha_html_code.htm
3. Copy and save the site key and secret, see https://docs.eu-captcha.eu/Content/views/1-Dashboard/sitekey_tabs/Captcha_details_tab.htm.
4. Open your PHP Project.
5. Add the site key and secret to the code.

### Example
```php
use Myrasec\EU_Captcha;

$captcha = new EU_Captcha([
    'sitekey' => '<site key for your website>',
    'secret' => '<secret for the site key of your website>',
    'failDefault' => true,
]);

$res = $captcha->validate($_POST["eu-captcha-response"]);

if (!$res->success()) {
  // reject form submission / API call
  return;
}
```