/*-----------------------------------
TEXT TRANSLATION SNIPPETS FOR GOBRIK.com
-----------------------------------*/

// Ampersand (&): Should be escaped as &amp; because it starts HTML character references.
// Less-than (<): Should be escaped as &lt; because it starts an HTML tag.
// Greater-than (>): Should be escaped as &gt; because it ends an HTML tag.
// Double quote ("): Should be escaped as &quot; when inside attribute values.
// Single quote/apostrophe ('): Should be escaped as &#39; or &apos; when inside attribute values.
// Backslash (\): Should be escaped as \\ in JavaScript strings to prevent ending the string prematurely.
// Forward slash (/): Should be escaped as \/ in </script> tags to prevent prematurely closing a script.


// English translations
const en_Page_Translations = {

    // first activate page
    "0001-activate-notice": "since you've last logged in, we've made a massive upgrade to GoBrik.",
    "0002-activate-explantion-1": "Our old version of GoBrik ran on corporate servers and code. We've let this pass pass away.",
    "0002-activate-explantion-2": "In its place, we have migrated all our data to our own independent, self-run server.  Our new GoBrik 3.0 is now 100% open source fully focused on ecological accountability.  As an alternative to logging in with Google, Apple or Facebook we've developed our own login system (what we're calling Buwana accounts).  To join us on the regenerated GoBrik with please take a minute to upgrade your old ",
    "0002-activate-explantion-3": "account to our new system.",
    "0003-activate-button": '<input type="submit" id="submit-button" value="🍃 Upgrade Account!" class="submit-button activate">',
    "0004-buwana-accounts": "Buwana accounts are designed with ecology, security, and privacy in mind. Soon, you'll be able to login to other great regenerative apps movement in the same way you login to GoBrik!.",
    "0005-new-terms": "New GoBrik Terms of Service",
    "0005-regen-blog": "Why? Read our 'Great GoBrik Regeneration' blog",
    "0006-github-code": "New Github Source Code Repository",
    "0007-not-interested": "If you're not interested and would like your old ",
    "0009-that-too": " account completely deleted, you can do that too.",
    "0010-delete-button": "Delete My Account",
    "0011-warning": "WARNING: This cannot be undone.",

    // activate-2
    "001-set-your-pass": "Set Your New Password",
    "002-to-get-going": " To get going with your upgraded account please set a new password...",
    "007-set-your-pass": "Set your password:",
    "008-password-advice": "🔑 Your password must be at least 6 characters.",
    "009-confirm-pass": "Confirm Your Password:",
    "010-pass-error-no-match": "👉 Passwords do not match.",
    "013-by-registering": "By registering today, I agree to the <a href=\"#\" onclick=\"showModalInfo('terms')\" class=\"underline-link\">GoBrik Terms of Service</a>",
    "014-i-agree-newsletter": "Please send me the <a href=\"#\" onclick=\"showModalInfo('earthen', 'en')\" class=\"underline-link\">Earthen newsletter</a> for app, ecobrick, and earthen updates",
    "015-confirm-pass-button": '<input type="submit" id="submit-button" value="Confirm Password" class="submit-button disabled">',

    // confirm email
    "001-alright": "Alright",
    "002-lets-confirm": "let's confirm your email.",
    "003-to-create": "To create your Buwana GoBrik account we need to confirm your chosen credential. This is how we'll keep in touch and keep your account secure. Click the send button and we'll send an account activation code to:",
    "004-send-email-button": '<input type="submit" name="send_email" id="send_email" value="📨 Send Code" class="submit-button activate">',
    "006-enter-code": "Please enter your code:",
    "007-check-email": "Check your email",
    "008-for-your-code": "for your account confirmation code. Enter it here:",
    "009-no-code": "Didn't get your code? You can request a resend of the code in",
    "010-email-no-longer": "Do you no longer use this email address?<br>If not, you'll need to <a href=\"signup.php\">create a new account</a> or contact our team at support@gobrik.com.",
    "011-change-email": "Want to change your email?",
    "012-go-back-new-email": "Go back to enter a different email address.",

    // activate-3.php
    "001-password-set": "your password is set.",
   "011-your-local-area": "Where is your home?",
  "011-location-full-caption": "Start typing your home location (without the street location!), and we'll fill in the rest.  Data source: OpenStreetMap API.",
  "000-field-required-error": "This field is required.",
  '011-watershed-select': 'To what river/stream watershed does your local water flow?',
  '011b-select-river': '👉 Select river/stream...',
  '011c-unknown': "I don't know",
  '011d-unseen': "I don't see my local river/stream",
  '011e-no-watershed': 'No watershed',
  '012-river-basics': 'ℹ️ <a href="#" onclick="showModalInfo(\'watershed\', \'<?php echo $lang; ?>\')" class="underline-link">Watersheds</a> provide a great non-political way to localize our users by ecological region!  The map shows rivers and streams around you.  Choose the one to which your water flows.',

  "012-community-name": "Select and confirm your GoBrik community:",
  "012-community-caption": "Start typing to see and select a community.  Only GoBrik 2.0 currently available.  Soon you'll be able to add a new community!",
  "016-next-button": "<input type=\"submit\" id=\"submit-button\" value=\"Next ➡️\" class=\"submit-button enabled\">"

};



