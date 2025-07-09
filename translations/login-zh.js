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

const zh_Page_Translations = {

    "001-cant-find": "🤔 数据库中找不到此凭证。",
    "002-password-is-wrong": "👉 密码错误。",
    "003-forgot-your-password": "忘记密码了吗？",
    "000-reset-it": "重设密码。",
    "003-code-status": "登录代码将发送到你的电子邮件。",
    "004-login-button": '<input type="submit" id="submit-password-button" value="登录" class="login-button-75">',
    "005-password-field-placeholder": '<input type="password" id="password" name="password" required placeholder="你的密码...">'
    "006-too-many-attempts": "⚠️ 尝试失败次数过多。请在10分钟后再试。"
};

