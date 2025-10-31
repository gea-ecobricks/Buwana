/*-----------------------------------------

CODE PROCESSING

---------------------------------------- */



/* Code entry and processing for 2FA */

document.addEventListener('DOMContentLoaded', function () {
    const codeInputs = document.querySelectorAll('.code-box');
    const sendCodeButton = document.getElementById('send-code-button');
    const codeErrorDiv = document.getElementById('code-error');
    const codeStatusDiv = document.getElementById('code-status');
    const credentialKeyInput = document.getElementById('credential_key');

    // Function to move focus to the next input
    function moveToNextInput(currentInput, nextInput) {
        if (nextInput) {
            nextInput.focus();
        }
    }

    // Setup each input box
    codeInputs.forEach((input, index) => {
        // Handle paste event separately
        input.addEventListener('paste', (e) => handlePaste(e));

        // Handle input event for typing data
        input.addEventListener('input', () => handleInput(input, index));

        // Handle backspace for empty fields to jump back to the previous field
        input.addEventListener('keydown', (e) => handleBackspace(e, input, index));
    });

    // Function to handle paste event
    function handlePaste(e) {
        const pastedData = e.clipboardData.getData('text').slice(0, codeInputs.length);
        [...pastedData].forEach((char, i) => codeInputs[i].value = char);
        codeInputs[Math.min(pastedData.length, codeInputs.length) - 1].focus();
        validateCode();
        e.preventDefault();
    }

    // Function to handle input event for typing data
    function handleInput(input, index) {
        if (input.value.length === 1 && index < codeInputs.length - 1) {
            moveToNextInput(input, codeInputs[index + 1]);
        }
        if ([...codeInputs].every(input => input.value.length === 1)) {
            validateCode();
        }
    }

    // Function to handle backspace for empty fields to jump back to the previous field
    function handleBackspace(e, input, index) {
        if (e.key === "Backspace" && input.value === '' && index > 0) {
            codeInputs[index - 1].focus();
        }
    }

    // Function to validate the code if all fields are filled
    function validateCode() {
        const fullCode = [...codeInputs].map(input => input.value.trim()).join('');
        if (fullCode.length === codeInputs.length) {
            ajaxValidateCode(fullCode);
        }
    }

    // Function to handle AJAX call to validate the code
    function ajaxValidateCode(code) {
        fetch('https:/buwana.ecobricks.org/processes/code_login_process.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `code=${code}&credential_key=${credentialKeyInput.value}`
        })
            .then(response => response.json())
            .then(data => handleAjaxResponse(data));
    }

    // Function to handle AJAX response
    function handleAjaxResponse(data) {
        if (data.status === 'invalid') {
            showErrorMessage("👉 Code is wrong.", 'Incorrect Code', 'red');
            shakeElement(document.getElementById('code-form'));
            clearCodeInputs();
        } else if (data.status === 'success') {
            showSuccessMessage('Code correct! Logging in...');
            window.location.href = data.redirect;
        }
    }

    // Function to show error messages
    function showErrorMessage(errorText, statusText, color) {
        codeErrorDiv.textContent = errorText;
        codeStatusDiv.textContent = statusText;
        codeStatusDiv.style.color = color;
    }

    // Function to show success messages
    function showSuccessMessage(text) {
        codeStatusDiv.textContent = text;
        codeStatusDiv.style.color = 'green';
    }

    // Function to clear all code inputs
    function clearCodeInputs() {
        codeInputs.forEach(input => input.value = '');
        codeInputs[0].focus();
    }

    // Function to handle the shaking animation
    function shakeElement(element) {
        element.classList.add('shake');
        setTimeout(() => element.classList.remove('shake'), 400);
    }

    // Function to handle the sending of the code
    function submitCodeForm(event) {
        event.preventDefault();
        setButtonState("Sending...", true);
        fetch('https://buwana.ecobricks.org/processes/code_process.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ 'credential_key': credentialKeyInput.value })
        })
            .then(response => response.text())
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    handleCodeResponse(data);
                } catch (error) {
                    showAlertAndResetButton('An unexpected error occurred.');
                }
            })
            .catch(() => showAlertAndResetButton('An unexpected error occurred.'));
    }

    // Function to handle the response after code submission
    function handleCodeResponse(data) {
        codeErrorDiv.textContent = '';
        codeErrorDiv.style.display = 'none';

        switch (data.status) {
            case 'empty_fields':
                alert('Please enter your credential key.');
                resetSendCodeButton();
                break;
            case 'activation_required':
                window.location.href = data.redirect || `https://gobrik.com/en/activate.php?id=${data.id}`;  //STILL RElevant?
                break;
            case 'not_found':
            case 'crednotfound':
                showErrorAndResetButton('Sorry, no matching email was found.');
                break;
            case 'credfound':
                handleSuccessfulCodeSend();
                break;
            default:
                showAlertAndResetButton('An error occurred. Please try again later.');
                break;
        }
    }

    // Function to handle successful code send
    function handleSuccessfulCodeSend() {
        sendCodeButton.value = "✅ Code sent!";
        codeStatusDiv.textContent = 'Code is sent! Check your email.';
        codeStatusDiv.style.display = 'block';
        codeStatusDiv.style.color = '';
        resendCountDown(60, codeStatusDiv, sendCodeButton);
        enableCodeEntry();
    }

    // Function to enable typing in code fields
    function enableCodeEntry() {
        codeInputs.forEach(codeBox => {
            codeBox.style.pointerEvents = 'auto';
            codeBox.style.cursor = 'text';
            codeBox.style.opacity = '1';
        });
    }

    // Function to reset the send code button to its original state
    function resetSendCodeButton() {
        setButtonState("📨 Send Code Again", false);
    }

    // Function to set button state
    function setButtonState(text, isDisabled) {
        sendCodeButton.value = text;
        sendCodeButton.disabled = isDisabled;
        sendCodeButton.style.pointerEvents = isDisabled ? 'none' : 'auto';
        sendCodeButton.style.cursor = isDisabled ? 'auto' : 'pointer';
    }

    // Function to handle alert and reset button
    function showAlertAndResetButton(message) {
        alert(message);
        resetSendCodeButton();
    }

    // Function to show error and reset button
    function showErrorAndResetButton(message) {
        codeErrorDiv.textContent = message;
        codeErrorDiv.style.display = 'block';
        resetSendCodeButton();
    }

    // Function for resend countdown
    function resendCountDown(seconds, displayElement, sendCodeButton) {
        let remaining = seconds;
        const interval = setInterval(() => {
            displayElement.style.color = '';
            displayElement.textContent = `Resend code in ${remaining--} seconds.`;
            if (remaining < 0) {
                clearInterval(interval);
                displayElement.textContent = 'You can now resend the code.';
                resetSendCodeButton();
            }
        }, 1000);
    }

    // Attach submit handler to the send code button
    sendCodeButton.addEventListener('click', submitCodeForm);

});




/*---------------------------------------

TOGGLE LOGIN BUTTON

------------------------------------*/




/*---------------------------------------

TOGGLE LOGIN BUTTON

------------------------------------*/



document.addEventListener('DOMContentLoaded', function () {
    const passwordForm = document.getElementById('password-form');
    const codeForm = document.getElementById('code-form');
    const passwordToggle = document.getElementById('password');
    const codeToggle = document.getElementById('code');
    const submitPasswordButton = document.getElementById('submit-password-button');
    const sendCodeButton = document.getElementById('send-code-button');
    const passwordField = document.getElementById('password');
    const form = document.getElementById('login');

    // Function to update the form visibility and toggle required attribute based on toggle state
    function updateFormVisibility() {
        if (passwordToggle.checked) {
            // Fade out the code form and then hide it
            codeForm.style.opacity = '0';
            setTimeout(() => {
                codeForm.style.display = 'none';
                passwordForm.style.display = 'block';
                // Fade in the password form
                setTimeout(() => {
                    passwordForm.style.opacity = '1';
                }, 10);
            }, 300); // Time for the fade-out transition

        } else if (codeToggle.checked) {
            // Fade out the password form and then hide it
            passwordForm.style.opacity = '0';
            setTimeout(() => {
                passwordForm.style.display = 'none';
                codeForm.style.display = 'block';
                // Fade in the code form
                setTimeout(() => {
                    codeForm.style.opacity = '1';
                }, 10);
            }, 300); // Time for the fade-out transition
        }
    }

    // Function to update the visibility of the submit buttons
    function updateButtonVisibility() {
        submitPasswordButton.style.display = 'none';

        if (passwordToggle.checked) {
            sendCodeButton.style.display = 'none';
            setTimeout(() => {
                submitPasswordButton.style.display = 'block';
                document.querySelectorAll('.login-button-75').forEach(el => {
                    el.style.display = 'block';
                });
            }, 600); // Delay for transition effect
        } else {

            setTimeout(() => {
                sendCodeButton.style.display = 'block';
                submitPasswordButton.style.display = 'none';
                document.querySelectorAll('.login-button-75').forEach(el => {
                    el.style.display = 'none';
                });

            }, 600); // Delay for transition effect
        }
    }

    // Event listener for toggle button clicks
    document.querySelectorAll('.toggle-button').forEach(button => {
        button.addEventListener('click', () => {
            if (button.classList.contains('password')) {
                passwordToggle.checked = true;
                codeToggle.checked = false;
            } else {
                codeToggle.checked = true;
                passwordToggle.checked = false;
            }

            // Update form action, visibility, and buttons based on the selected toggle
            updateButtonVisibility();
            updateFormAction();
            updateFormVisibility();

        });
    });



    function updateFormAction() {

        if (codeToggle.checked) {
            // If the code option is selected
            passwordField.removeAttribute('required');
            form.action = 'https://buwana.ecobricks.org/processes/code_process.php';
        } else if (passwordToggle.checked) {
            // If the password option is selected
            passwordField.setAttribute('required', 'required');
            form.action = 'https://buwana.ecobricks.org/processes/login_process_jwt.php';
        }
    }
});




/* --------------------------------------

STATUS MESSAGES


-------------------------------------- */



document.addEventListener("DOMContentLoaded", function () {
    // Function to extract the query parameters from the URL
    function getQueryParam(param) {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(param);
    }

    // Function to get status messages
    function getStatusMessages(status, lang, firstName = '') {
        const messages = {
            logout: {
                en: { main: "You're logged out.", sub: "When you're ready $first_name, login again with your account credentials." },
                fr: { main: "Vous avez été déconnecté.", sub: "Quand vous êtes prêt $first_name, reconnectez-vous avec vos identifiants." },
                es: { main: "Has cerrado sesión.", sub: "Cuando estés listo $first_name, vuelve a iniciar sesión con tus credenciales." },
                id: { main: "Anda telah keluar.", sub: "Saat Anda siap $first_name, masuk kembali dengan kredensial akun Anda." },
                de: { main: "Du bist abgemeldet.", sub: "Wenn du bereit bist $first_name, melde dich erneut mit deinen Kontodaten an." },
                ar: { main: "لقد تم تسجيل خروجك.", sub: "عندما تكون جاهزًا $first_name، سجّل الدخول مرة أخرى باستخدام بيانات اعتماد حسابك." },
                zh: { main: "您已登出。", sub: "准备好后，$first_name，请再次使用您的帐户凭据登录。" }
            },
            firsttime: {
                en: { main: "Your Buwana Account is Created! 🎉", sub: "And your Earthen subscriptions are confirmed. Now $first_name, please login again with your new account credentials." },
                fr: { main: "Votre compte Buwana est créé ! 🎉", sub: "Maintenant $first_name, connectez-vous avec vos nouvelles identifiants." },
                es: { main: "¡Tu cuenta Buwana está creada! 🎉", sub: "Y tus suscripciones Earthen están confirmadas. Ahora $first_name, inicia sesión de nuevo con tus nuevas credenciales." },
                id: { main: "Akun Buwana Anda telah dibuat! 🎉", sub: "Dan langganan Earthen Anda telah dikonfirmasi. Sekarang $first_name, silakan masuk lagi dengan kredensial akun baru Anda." },
                de: { main: "Dein Buwana-Konto wurde erstellt! 🎉", sub: "Und deine Earthen-Abonnements sind bestätigt. Jetzt $first_name, bitte melde dich erneut mit deinen neuen Kontodaten an." },
                ar: { main: "تم إنشاء حساب بوانا الخاص بك! 🎉", sub: "وتم تأكيد اشتراكاتك في Earthen. الآن $first_name، الرجاء تسجيل الدخول مرة أخرى باستخدام بيانات اعتماد حسابك الجديدة." },
                zh: { main: "您的 Buwana 账户已创建！🎉", sub: "您的 Earthen 订阅已确认。现在 $first_name，请使用新的账户凭据再次登录。" }
            },
            upgraded: {
                en: { main: "You're now set up to use $app_display_name", sub: "Your Buwana account can now be used to login to $app_display_name" },
                fr: { main: "Vous êtes maintenant configuré pour utiliser $app_display_name", sub: "Votre compte Buwana peut maintenant être utilisé pour se connecter à $app_display_name" },
                es: { main: "Ahora estás listo para usar $app_display_name", sub: "Tu cuenta Buwana ahora puede usarse para iniciar sesión en $app_display_name" },
                id: { main: "Anda sekarang siap menggunakan $app_display_name", sub: "Akun Buwana Anda sekarang dapat digunakan untuk masuk ke $app_display_name" },
                de: { main: "Du bist nun bereit, $app_display_name zu verwenden", sub: "Dein Buwana-Konto kann nun verwendet werden, um dich bei $app_display_name anzumelden." },
                ar: { main: "أصبح بإمكانك الآن استخدام $app_display_name", sub: "يمكن الآن استخدام حساب بوانا الخاص بك لتسجيل الدخول إلى $app_display_name" },
                zh: { main: "您现在已准备好使用 $app_display_name", sub: "您的 Buwana 账户现在可以用于登录 $app_display_name" }
            },
            default: {
                en: { main: "Welcome back!", sub: "Please login again with your account credentials." },
                fr: { main: "Bon retour !", sub: "Veuillez vous reconnecter avec vos identifiants." },
                es: { main: "¡Bienvenido de nuevo!", sub: "Por favor, inicia sesión nuevamente con tus credenciales." },
                id: { main: "Selamat datang kembali!", sub: "Silakan masuk kembali dengan kredensial akun Anda." },
                de: { main: "Willkommen zurück!", sub: "Bitte melde dich erneut mit deinen Kontodaten an." },
                ar: { main: "مرحبًا بعودتك!", sub: "يرجى تسجيل الدخول مرة أخرى باستخدام بيانات اعتماد حسابك." },
                zh: { main: "欢迎回来！", sub: "请再次使用您的账户凭据登录。" }
            }
        };

        const selected = messages[status] && messages[status][lang]
            ? messages[status][lang]
            : messages.default[lang] || messages.default.en;

        const main = selected.main
            .replace('$app_display_name', appDisplayName)
            .replace('$first_name', firstName);
        const sub = selected.sub
            .replace('$app_display_name', appDisplayName)
            .replace('$first_name', firstName);

        return { main, sub };
    }

    // Consolidated function to handle error responses and show the appropriate error div
    function handleErrorResponse(errorType) {
        // Hide both error divs initially
        document.getElementById('password-error').style.display = 'none';
        document.getElementById('no-buwana-email').style.display = 'none';

        // Show the appropriate error div based on the errorType
        if (errorType === 'invalid_password' || errorType === 'too_many_attempts') {
            const errorDiv = document.getElementById('password-error');

            if (errorType === 'too_many_attempts') {
                errorDiv.setAttribute('data-lang-id', '006-too-many-attempts');
                if (window.translations && window.translations['006-too-many-attempts']) {
                    errorDiv.textContent = window.translations['006-too-many-attempts'];
                } else {
                    errorDiv.textContent = '⚠️ Too many failed attempts. Try again in 10 minutes.';
                }
            } else {
                errorDiv.setAttribute('data-lang-id', '002-password-is-wrong');
                if (window.translations && window.translations['002-password-is-wrong']) {
                    errorDiv.textContent = window.translations['002-password-is-wrong'];
                } else {
                    errorDiv.textContent = '👉 Password is wrong.';
                }
            }

            errorDiv.style.display = 'block'; // Show password error
            shakeElement(document.getElementById('password-form'));
        } else if (errorType === 'invalid_user' || errorType === 'invalid_credential') {

            shakeElement(document.getElementById('credential-input-field'));
            document.getElementById('no-buwana-email').style.display = 'block'; // Show email error for invalid user/credential
        }
    }

    // Get the values from the URL query parameters
    const status = window.status || getQueryParam('status') || ''; // status like 'loggedout', 'firsttime', etc.
    const lang = document.documentElement.lang || 'en'; // Get language from the <html> tag or default to 'en'
    const firstName = window.firstName || getQueryParam('firstName') || getQueryParam('firstname') || '';
    const credentialKey = window.credentialKey || getQueryParam('key'); // credential_key
    const code = window.code || getQueryParam('code'); // Get the code from the URL
    const buwanaId = window.buwanaId || getQueryParam('id'); // Get the id from the URL

    function updateStatusMessages() {
        const { main, sub } = getStatusMessages(status, lang, firstName);
        document.getElementById('status-message').textContent = main;
        document.getElementById('sub-status-message').textContent = sub;
    }

    if (window.translations) {
        updateStatusMessages();
    } else {
        document.addEventListener('translationsLoaded', updateStatusMessages);
    }

    // Fill the credential_key input field if present in the URL
    if (credentialKey) {
        document.getElementById('credential_key').value = credentialKey;
    }

    // Handle form submission validation
    document.getElementById('login').addEventListener('submit', function (event) {
        var credentialValue = document.getElementById('credential_key').value;
        var password = document.getElementById('password').value;

        // Simple form validation before submitting
        if (credentialValue === '' || password === '') {
            event.preventDefault();
            handleErrorResponse('invalid_password'); // Show password error if fields are empty
            shakeElement(password-form);
        }
    });

    // Handle errors based on status parameter in URL
    const errorType = status; // Status used as errorType (e.g., invalid_password, invalid_user)
    if (errorType) {
        handleErrorResponse(errorType);
    }

/*----

AUTO CODE PROCESSING


 */
// Check if code and buwana_id are present in the URL for automatic code processing
    if (code && buwanaId) {
        // Update status messages
        document.getElementById('status-message').textContent = "Your Account is Ready!";
        document.getElementById('sub-status-message').textContent = "Please go ahead and login.";

        // Add a 0.3 sec pause
        setTimeout(() => {
            // Set the toggle to code
            document.getElementById('code-toggle').checked = true;

            // Run functions to update form and button visibility
            updateFormVisibility();
            updateButtonVisibility();

            // Update the sendCodeButton and codeStatusDiv
            const sendCodeButton = document.getElementById('send-code-button');
            const codeStatusDiv = document.getElementById('code-status');
            sendCodeButton.value = "Processing..."; // Indicate processing
            sendCodeButton.disabled = true; // Disable the button to prevent multiple submissions
            sendCodeButton.style.pointerEvents = 'none'; // Remove pointer events
            sendCodeButton.style.cursor = 'auto';
            codeStatusDiv.textContent = "Verifying your login code..."; // Update status message

            // Add another 0.3 sec pause before populating code fields
            setTimeout(() => {
                // Populate the five code-fields one by one with 0.2s pauses
                const codeInputs = document.querySelectorAll('.code-box');
                code.split('').forEach((digit, index) => {
                    if (index < codeInputs.length) {
                        setTimeout(() => {
                            codeInputs[index].value = digit;

                            // Simulate 'input' event to trigger listeners
                            const event = new Event('input', { bubbles: true });
                            codeInputs[index].dispatchEvent(event);

                            if (index === codeInputs.length - 1) {
                                // Run the function to process the login after all fields are filled
                                updateFormAction();
                            }
                        }, index * 200); // Pause 0.2s for each character
                    }
                });
            }, 300); // Pause for 0.3 seconds
        }, 300); // Initial pause for 0.3 seconds
    }

});


/*------------------------------

MORE TOGGLE PROCESSING


 ---------------------------------*/



function updateFormVisibility() {
    const passwordForm = document.getElementById('password-form');
    const codeForm = document.getElementById('code-form');
    const passwordToggle = document.getElementById('password');
    const codeToggle = document.getElementById('code');
    const submitPasswordButton = document.getElementById('submit-password-button');
    const sendCodeButton = document.getElementById('send-code-button');

    if (passwordToggle.checked) {
        // Fade out the code form and then hide it
        codeForm.style.opacity = '0';
        setTimeout(() => {
            codeForm.style.display = 'none';
            passwordForm.style.display = 'block';
            // Fade in the password form
            setTimeout(() => {
                passwordForm.style.opacity = '1';
            }, 10);
        }, 300); // Time for the fade-out transition

    } else if (codeToggle.checked) {
        // Fade out the password form and then hide it
        passwordForm.style.opacity = '0';
        setTimeout(() => {
            passwordForm.style.display = 'none';
            codeForm.style.display = 'block';
            // Fade in the code form
            setTimeout(() => {
                codeForm.style.opacity = '1';
            }, 10);
        }, 300); // Time for the fade-out transition
    }
}

// Function to update the visibility of the submit buttons
function updateButtonVisibility() {
    const passwordForm = document.getElementById('password-form');
    const codeForm = document.getElementById('code-form');
    const passwordToggle = document.getElementById('password');
    const codeToggle = document.getElementById('code');
    const submitPasswordButton = document.getElementById('submit-password-button');
    const sendCodeButton = document.getElementById('send-code-button');

    if (passwordToggle.checked) {
        sendCodeButton.style.display = 'none';
        setTimeout(() => {
            submitPasswordButton.style.display = 'block';
        }, 600); // Delay for transition effect
    } else {
        submitPasswordButton.style.display = 'none';
        setTimeout(() => {
            sendCodeButton.style.display = 'block';
        }, 600); // Delay for transition effect
    }
}

//
// function updateFormAction() {
//     const passwordForm = document.getElementById('password-form');
//     const codeForm = document.getElementById('code-form');
//     const passwordToggle = document.getElementById('password');
//     const codeToggle = document.getElementById('code');
//     const submitPasswordButton = document.getElementById('submit-password-button');
//     const sendCodeButton = document.getElementById('send-code-button');
//
//     const form = document.getElementById('login');
//     const passwordField = document.getElementById('password');
//
//     if (codeToggle.checked) {
//         // If the code option is selected
//         passwordField.removeAttribute('required');
//         form.action = 'https:/buwana.ecobricks.org/processes/code_process.php';
//         console.log("Code is checked.");
//     } else if (passwordToggle.checked) {
//         // If the password option is selected
//         passwordField.setAttribute('required', 'required');
//         form.action = 'https:/buwana.ecobricks.org/processes/login_process_jwt.php';
//         console.log("Password is checked.");
//     }
// }

/*-----------------------------------

CREDENTIALS MENU

-------------------------------- */




/*Trigger the credentials menu from the key symbol in the credentials field.*/

document.addEventListener("DOMContentLoaded", function () {
    const toggleSelectIcon = document.querySelector('.toggle-select-key');
    const dropdownMenu = document.getElementById('dropdown-menu');
    const credentialKeyInput = document.getElementById('credential_key');
    const dropdownItems = dropdownMenu.querySelectorAll('.dropdown-item');

    // Toggle dropdown menu visibility on click
    toggleSelectIcon.addEventListener('click', function () {
        dropdownMenu.style.display = dropdownMenu.style.display === 'none' ? 'block' : 'none';
    });

    // Close dropdown if clicked outside
    document.addEventListener('click', function (e) {
        if (!toggleSelectIcon.contains(e.target) && !dropdownMenu.contains(e.target)) {
            dropdownMenu.style.display = 'none';
        }
    });

    // Handle dropdown item selection
    dropdownItems.forEach(function (item) {
        item.addEventListener('click', function () {
            if (!item.classList.contains('disabled')) {
                credentialKeyInput.value = item.textContent.trim();
                dropdownMenu.style.display = 'none';
            }
        });
    });
});



/*------------------------------------------------

PASSWORD RESET

--------------------------------------------- */



/* PASSWORD RESET MODAL  */
function showPasswordReset(type, lang = '<?php echo $lang; ?>', email = '') {
    const modal = document.getElementById('form-modal-message');
    const photobox = document.getElementById('modal-photo-box');
    const messageContainer = modal.querySelector('.modal-message');
    let content = '';
    photobox.style.display = 'none';

    switch (type) {
        case 'reset':
            let title, promptText, buttonText, errorText;

            switch (lang) {
                case 'fr':
                    title = "<h1>🙈</h1>";
                    promptText = "Entrez votre email pour réinitialiser votre mot de passe :";
                    buttonText = "Réinitialiser le mot de passe";
                    errorText = "🤔 Hmmm... nous ne trouvons aucun compte utilisant cet email !";
                    break;
                case 'es':
                    title = "<h1>🙈</h1>";
                    promptText = "Ingrese su correo electrónico para restablecer su contraseña:";
                    buttonText = "Restablecer la contraseña";
                    errorText = "🤔 Hmmm... no podemos encontrar una cuenta que use este correo electrónico!";
                    break;
                case 'id':
                    title = "<h1>🙈</h1>";
                    promptText = "Masukkan email Anda untuk mengatur ulang kata sandi Anda:";
                    buttonText = "Atur Ulang Kata Sandi";
                    errorText = "🤔 Hmmm... kami tidak dapat menemukan akun yang menggunakan email ini!";
                    break;
                case 'de':
                    title = "<h1>🙈</h1>";
                    promptText = "Geben Sie Ihre E-Mail ein, um Ihr Passwort zurückzusetzen:";
                    buttonText = "Passwort zurücksetzen";
                    errorText = "🤔 Hmmm... wir können kein Konto finden, das diese E-Mail verwendet!";
                    break;
                case 'ar':
                    title = "<h1>🙈</h1>";
                    promptText = "أدخل بريدك الإلكتروني لإعادة تعيين كلمة المرور:";
                    buttonText = "إعادة تعيين كلمة المرور";
                    errorText = "🤔 هممم... لا يمكننا العثور على حساب يستخدم هذا البريد الإلكتروني!";
                    break;
                case 'zh':
                    title = "<h1>🙈</h1>";
                    promptText = "输入您的电子邮件以重置密码:";
                    buttonText = "重置密码";
                    errorText = "🤔 嗯...我们找不到使用该电子邮件的账户!";
                    break;
                default: // 'en'
                    title = "<h1>🙈</h1>";
                    promptText = "Enter your email to reset your password:";
                    buttonText = "Reset Password";
                    errorText = "🤔 Hmmm... we can't find an account that uses this email!";
                    break;
            }

            content = `
                <div style="text-align:center;width:100%;margin:auto;margin-top:10px;margin-bottom:10px;">
                    
                </div>
                <div class="reset-title">${title}</div>
                <form id="resetPasswordForm" action="https://buwana.ecobricks.org/processes/reset_pass.php" method="POST">
                    <div class="preview-text" style="font-size:medium;">${promptText}</div>
                    <input type="hidden" name="lang" value="${lang}">
                    <input type="email" name="email" required value="${email}">
                    <div style="text-align:center;width:100%;margin:auto;margin-top:10px;margin-bottom:10px;">
                        <div id="no-buwana-email" class="form-warning" style="display:none;margin-top:5px;margin-bottom:5px;" data-lang-id="010-no-buwana-email">${errorText}</div>
                        <button type="submit" class="submit-button enabled" style="min-width: 250px;margin-bottom: 177px;">${buttonText}</button>
                    </div>
                </form>
            `;
            break;

        case 'sent':
            let sentTitle, sentMessage;
            switch (lang) {
                case 'fr':
                    sentTitle = 'Envoyé !';
                    sentMessage = `Votre lien de réinitialisation a été envoyé à ${email}. Veuillez suivre le lien pour réinitialiser votre mot de passe Buwana.`;
                    break;
                case 'es':
                    sentTitle = '¡Enviado!';
                    sentMessage = `Tu restablecimiento de contraseña se ha enviado a ${email}. Por favor sigue el enlace allí para restablecer tu contraseña de Buwana.`;
                    break;
                case 'de':
                    sentTitle = 'Gesendet!';
                    sentMessage = `Dein Passwort-Reset wurde an ${email} gesendet. Bitte folge dem Link dort, um dein Buwana-Passwort zurückzusetzen.`;
                    break;
                case 'ar':
                    sentTitle = 'تم الإرسال!';
                    sentMessage = `تم إرسال رابط إعادة تعيين كلمة المرور إلى ${email}. يرجى اتباع الرابط هناك لإعادة تعيين كلمة مرور بوانا.`;
                    break;
                case 'zh':
                    sentTitle = '已发送！';
                    sentMessage = `重置密码的链接已发送到 ${email}。请按照其中的链接重置您的 Buwana 密码。`;
                    break;
                case 'id':
                    sentTitle = 'Terkirim!';
                    sentMessage = `Tautan pengaturan ulang kata sandi telah dikirim ke ${email}. Silakan ikuti tautan tersebut untuk mereset kata sandi Buwana Anda.`;
                    break;
                default:
                    sentTitle = 'Sent!';
                    sentMessage = `Your password reset has been sent to ${email}.  Please follow the link there to reset your Buwana password.`;
            }

            content = `
                <div style="text-align:center;width:100%;margin:auto;margin-top:10px;margin-bottom:10px;">

                </div>
                <div class="reset-title"><h1>🙉</h1></div>
                <div class="reset-title">${sentTitle}</div>
                <p style="text-align: center;margin-bottom: 177px">${sentMessage}</p>
            `;
            break;

        default:
            content = '<p>Invalid term selected.</p>';
    }

    messageContainer.innerHTML = content;

    modal.style.display = 'flex';
    document.getElementById('page-content').classList.add('blurred');
    document.getElementById('footer-full').classList.add('blurred');
    document.body.classList.add('modal-open');
}




/* --------------------------------


UNKNOWN

----------------------------------- */


window.onload = function() {
    const urlParams = new URLSearchParams(window.location.search);


//Relevant still?  Needs revision for status update of page variables.

    // Check if the 'reset_sent_to' parameter exists in the URL
    if (urlParams.has('reset_sent_to')) {
        const email = urlParams.get('reset_sent_to') || '';

        const lang = '<?php echo $lang; ?>';
        showPasswordReset('sent', lang, email);
    }

    // Check if the 'email_not_found' parameter exists in the URL
    if (urlParams.has('email_not_found')) {
        // Get the email from the URL parameters
        const email = urlParams.get('email') || '';

        // Get the language from the backend (PHP) or default to 'en'
        const lang = '<?php echo $lang; ?>'; // Make sure this is echoed from your PHP

        // Show the reset modal with the pre-filled email and appropriate language
//         showPasswordReset('reset', lang, email);

        // Wait for the modal to load, then display the "email not found" error message
        setTimeout(() => {
            const noBuwanaEmail = document.getElementById('no-buwana-email');
            if (noBuwanaEmail) {
                noBuwanaEmail.style.display = 'block';
            }
        }, 100);
    }
};



// Function to enable typing in the code boxes
function enableCodeEntry() {
    const codeBoxes = document.querySelectorAll('.code-box');

    codeBoxes.forEach((box, index) => {
        box.classList.add('enabled');  // Enable typing by adding the 'enabled' class

        box.addEventListener('input', function() {
            if (box.value.length === 1 && index < codeBoxes.length - 1) {
                codeBoxes[index + 1].focus();  // Jump to the next box
            }
        });
    });

    // Set focus on the first box
    codeBoxes[0].focus();
}
