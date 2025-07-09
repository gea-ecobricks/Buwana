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


const fr_Page_Translations = {
    "001-cant-find": "🤔 Nous ne pouvons pas trouver cette information d'identification dans la base de données.",
    "002-password-is-wrong": "👉 Le mot de passe est incorrect.",
    "003-forgot-your-password": "Mot de passe oublié ?",
    "000-reset-it": "Réinitialisez-le.",
    "003-code-status": "Un code de connexion sera envoyé à votre adresse e-mail.",
    "004-login-button": '<input type="submit" id="submit-password-button" value="Connexion" class="login-button-75">',
    "005-password-field-placeholder": '<input type="password" id="password" name="password" required placeholder="Votre mot de passe...">',
    "006-too-many-attempts": "⚠️ Trop de tentatives échouées. Réessayez dans 10 minutes."
};

