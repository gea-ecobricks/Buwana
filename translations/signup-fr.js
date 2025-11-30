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
    "001-signup-heading": "Créez votre compte",
    "002-signup-subtext": "Rejoignez-nous sur GoBrik avec un compte Buwana — une alternative open source, pour la Terre, aux connexions d'entreprise.",
    "003-firstname": "Comment vous appelez-vous ?",
    "004-name-placeholder": '<input type="text" id="first_name" name="first_name" aria-label="Votre prénom" title="Obligatoire. Max 255 caractères." required placeholder="Votre nom...">',
    "000-name-field-too-long-error": "Le nom est trop long. Maximum 255 caractères.",
    "005b-name-error": "L'entrée contient des caractères non valides. Veuillez éviter les guillemets, les barres obliques et les signes supérieur à.",
    "006-credential-choice": "Votre identifiant préféré :",
    "007-way-to-contact": "Vous utiliserez cet identifiant pour vous connecter et recevoir des messages de GoBrik.",
    "016-submit-to-password": "Suivant ➡️",

    /* Page suivante : Signup-2 - Définissez votre nom et email */

    "001-setup-access-heading": "Configurez votre accès",
    "002-setup-access-heading-a": "utilisons votre ",
    "003-setup-access-heading-b": "comme moyen d'enregistrement et façon de vous contacter.",
    "004-your": "Votre",
    "004b-please": " s'il vous plaît :",
    "010-duplicate-email": "🚧 Oups ! Il semble que cette adresse e-mail soit déjà utilisée par un compte Buwana. Veuillez en choisir une autre.",
    "010-gobrik-duplicate": "Il semble que cet e-mail soit déjà utilisé avec un compte GoBrik existant. Veuillez <a href=\"login.php\" class=\"underline-link\">vous connecter avec cet e-mail pour mettre à jour votre compte.</a>",
    "006-email-sub-caption": "💌 C'est ainsi que nous vous contacterons pour confirmer votre compte",
    "007-set-your-pass": "Définissez votre mot de passe :",
    "008-password-advice": "🔑 Votre mot de passe doit comporter au moins 6 caractères.",
    "009-confirm-pass": "Confirmez votre mot de passe :",
    "010-pass-error-no-match": "👉 Les mots de passe ne correspondent pas.",
    "011-prove-human": "Veuillez prouver que vous êtes humain en tapant le mot \"écobrique\" ci-dessous :",
    "012-fun-fact": "🤓 Fait amusant :",
    "012b-is-spelled": " s'écrit sans espace, majuscule ou trait d'union !",
    "013-by-registering": "En vous inscrivant aujourd'hui, j'accepte les <a href=\"#\" onclick=\"showModalInfo('terms', '<?php echo $lang; ?>')\" class=\"underline-link\">Conditions d'utilisation de GoBrik</a>",
    "014-i-agree-newsletter": "J'accepte de recevoir la <a href=\"#\" onclick=\"showModalInfo('earthen', '<?php echo $lang; ?>')\" class=\"underline-link\">newsletter Earthen</a> pour les mises à jour sur l'application, les écobriques et la terre"
};



