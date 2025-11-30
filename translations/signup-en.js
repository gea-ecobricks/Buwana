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

const en_Page_Translations = {
    "001-signup-heading": "Create Account",
    "002-signup-subtext": " uses Buwana— a powerful and private, open-source and for-Earth account system that powers regenerative apps.",
    "003-firstname": "What's your name?",
    "000-name-field-too-long-error": "The entry is too long. Max 255 characters.",
    "005b-name-error": "The entry contains invalid characters. Avoid quotes, slashes, and greater-than signs please.",
    "006-credential-choice": "Select how you register...",
    "007-way-to-contact": "We'll send your account confirmation messages this way. Later you'll login this way.",
    "016-submit-to-password": "Next",

    // Next page: Signup-2 - Set your name and email
    "001-register-by": "Register by",
    "002-now-lets-use": "Let's set you up on ",
    "003-to-register-on": "to register on ",
    "004-your": "Your",
    "004b-please": " please:",
    "010-duplicate-email": "🚧 Whoops! Looks like that e-mail address is already being used by a Buwana Account. Please choose another.",
    "010-gobrik-duplicate": "It looks like this email is already being used with a legacy GoBrik account. Please <a href=\"login.php\" class=\"underline-link\">login with this email to upgrade your account.</a>",
    "006-email-sub-caption": "💌 This is the way we will contact you to confirm your account",
    "007-set-your-pass": "Set your password:",
    "008-password-advice": "🔑 Your password must be at least 6 characters.",
    "009-confirm-pass": "Confirm Your Password:",
    "010-pass-error-no-match": "👉 Passwords do not match.",
    "011-human-check": "Human check:",
    "011-prove-human": "Type the word \"ecobrick\"...",
    "011b-prove-human": "This is a little test to see if you're human!",

    "012-fun-fact": "🤓 Fun fact:",
    "012b-is-spelled": " is spelled without a space, capital or hyphen!",
    "013-by-registering": "By registering today, I agree to the ",
    "014-terms-of-use": "Terms of Use",

    "000-browser-back-link": "<p style=\"font-size: medium;\">Need to correct something?<a href=\"#\" onClick=\"browserBack(event)\">Go back ↩️</a></p>"
};


