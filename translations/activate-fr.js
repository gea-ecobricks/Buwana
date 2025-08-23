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

    // First activate page
    "0001-activate-notice": "Depuis votre dernière connexion, nous avons apporté une mise à jour massive à GoBrik.",
    "0002-activate-explantion-1": "Notre ancienne version de GoBrik fonctionnait sur des serveurs et des codes d'entreprises. Nous avons laissé cela disparaître.",
    "0002-activate-explantion-2": "À sa place, nous avons migré toutes nos données vers notre propre serveur autonome. Notre nouveau GoBrik 3.0 est maintenant 100 % open source et entièrement axé sur la responsabilité écologique. Comme alternative à la connexion avec Google, Apple ou Facebook, nous avons développé notre propre système de connexion (que nous appelons comptes Buwana). Pour nous rejoindre sur le GoBrik régénéré, prenez un moment pour mettre à jour votre ",
"0002-activate-explantion-3": "ancien compte vers notre nouveau système.",
"0003-activate-button": '<input type="submit" id="submit-button" value="🍃 Mettre à niveau le compte !" class="submit-button activate">',
    "0004-buwana-accounts": "Les comptes Buwana sont conçus en tenant compte de l'écologie, de la sécurité et de la vie privée. Bientôt, vous pourrez vous connecter à d'autres applications de régénération de la même manière que vous vous connectez à GoBrik !",
    "0005-new-terms": "Nouveaux termes et conditions de GoBrik",
    "0005-regen-blog": "Pourquoi ? Lisez notre blog 'La Grande Régénération de GoBrik'.",
    "0006-github-code": "Nouveau dépôt de code source sur Github",
    "0007-not-interested": "Si vous n'êtes pas intéressé et souhaitez que votre ancien ",
    "0009-that-too": " compte soit complètement supprimé, vous pouvez également le faire.",
    "0010-delete-button": "Supprimer mon compte",
    "0011-warning": "AVERTISSEMENT : Cela ne peut pas être annulé.",

    // Activate-2
    "001-set-your-pass": "Définissez votre nouveau mot de passe",
    "002-to-get-going": " Pour commencer avec votre compte mis à niveau, veuillez définir un nouveau mot de passe...",
    "007-set-your-pass": "Définissez votre mot de passe :",
    "008-password-advice": "🔑 Votre mot de passe doit comporter au moins 6 caractères.",
    "009-confirm-pass": "Confirmez votre mot de passe :",
    "010-pass-error-no-match": "👉 Les mots de passe ne correspondent pas.",
    "013-by-registering": "En m'inscrivant aujourd'hui, j'accepte les <a href=\"#\" onclick=\"showModalInfo('terms')\" class=\"underline-link\">Conditions d'utilisation de GoBrik</a>",
    "014-i-agree-newsletter": "Veuillez m'envoyer la <a href=\"#\" onclick=\"showModalInfo('earthen', 'fr')\" class=\"underline-link\">newsletter Earthen</a> pour les mises à jour sur les applications, les écobriques et les projets en terre",
    "015-confirm-pass-button": '<input type="submit" id="submit-button" value="Confirmer le mot de passe" class="submit-button disabled">',

    // Confirm email
    "001-alright": "D'accord",
    "002-lets-confirm": "confirmons votre email.",
    "003-to-create": "Pour créer votre compte Buwana GoBrik, nous devons confirmer vos identifiants choisis. C'est ainsi que nous resterons en contact et que votre compte restera sécurisé. Cliquez sur le bouton d'envoi et nous vous enverrons un code d'activation de compte à l'adresse suivante :",
    "004-send-email-button": '<input type="submit" name="send_email" id="send_email" value="📨 Envoyer Code" class="submit-button activate">',
    "006-enter-code": "Veuillez entrer votre code :",
    "007-check-email": "Vérifiez votre e-mail",
    "008-for-your-code": "pour votre code de confirmation de compte. Entrez-le ici :",
    "009-no-code": "Vous n'avez pas reçu votre code ? Vous pouvez demander un renvoi du code dans",
    "010-email-no-longer": "N'utilisez-vous plus cette adresse e-mail ?<br>Si non, vous devrez <a href=\"signup.php\">créer un nouveau compte</a> ou contacter notre équipe à l'adresse support@gobrik.com.",
    "011-change-email": "Voulez-vous changer votre adresse e-mail ?",
    "012-go-back-new-email": "Retournez pour entrer une autre adresse e-mail.",

    // Activate-3.php
"001-password-set": "votre mot de passe est défini.",
"011-your-local-area": "Quelle est votre zone locale ?",
"011-location-full-caption": "Commencez à taper le nom de votre zone locale, et nous compléterons le reste en utilisant l'API OpenStreetMap open source et non corporative.",
"000-field-required-error": "Ce champ est obligatoire.",
 '011-watershed-select': 'Quelle est votre bassin versant ? Vers quel fleuve/rivière coule votre eau locale ?',
  '011b-select-river': '👉 Sélectionnez rivière/fleuve...',
  '011c-unknown': 'Je ne sais pas',
  '011d-unseen': 'Je ne vois pas ma rivière/fleuve locale',
  '011e-no-watershed': 'Pas de bassin versant',
  '012-river-basics': 'ℹ️ <a href="#" onclick="showModalInfo(\'watershed\', \'<?php echo $lang; ?>\')" class="underline-link">Les bassins versants</a> offrent un excellent moyen non politique de localiser nos utilisateurs par région écologique. La carte montre les rivières et fleuves autour de vous. Choisissez celui vers lequel coule votre eau.',
"012-community-name": "Sélectionnez et confirmez votre communauté GoBrik :",
"012-community-caption": "Commencez à taper pour voir et sélectionner une communauté. Seule GoBrik 2.0 est actuellement disponible. Bientôt, vous pourrez ajouter une nouvelle communauté !",
"016-next-button": "<input type=\"submit\" id=\"submit-button\" value=\"Suivant ➡️\" class=\"submit-button enabled\">"



};

