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

const id_Page_Translations = {

    "001-cant-find": "🤔 Kami tidak dapat menemukan kredensial ini di database.",
    "002-password-is-wrong": "👉 Kata sandi salah.",
    "003-forgot-your-password": "Lupa kata sandi Anda?",
    "000-reset-it": "Atur ulang.",
    "003-code-status": "Kode untuk masuk akan dikirim ke email Anda.",
    "004-login-button": '<input type="submit" id="submit-password-button" value="Masuk" class="login-button-75">',
    "005-password-field-placeholder": '<input type="password" id="password" name="password" required placeholder="Kata sandi Anda...">',
    "006-too-many-attempts": "⚠️ Terlalu banyak percobaan gagal. Coba lagi dalam 10 menit."
};
