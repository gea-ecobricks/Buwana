
<!--  Set any page specific graphics to preload-->

<!--  Set any page specific graphics to preload
<link rel="preload" as="image" href="../webps/ecobrick-team-blank.webp" media="(max-width: 699px)">
<link rel="preload" as="image" href="../svgs/richard-and-team-day.svg">
<link rel="preload" as="image" href="../svgs/richard-and-team-night.svg">
<link rel="preload" as="image" href="../webps/biosphere2.webp">
<link rel="preload" as="image" href="../webps/biosphere-day.webp">-->

<?php require_once ("../meta/login-$lang.php");?>


<STYLE>











































#main {
    height: fit-content;
}


.module-btn {
  background: var(--emblem-green);
  width: 100%;
  display: flex;
}

.module-btn:hover {
  background: var(--emblem-green-over);
}

#splash-bar {
  background-color: var(--top-header);
  filter: none !important;
  margin-bottom: -200px !important;
}



.input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

#dropdown-menu {
    display: none;
    position: absolute;
    top: 100%;
    right: 0;
    background-color: white;
    border: 1px solid #ccc;
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    z-index: 100;
    width: 200px;
}

.dropdown-item {
    padding: 10px;
    cursor: pointer;
}

.dropdown-item.disabled {
    color: #999;
    cursor: not-allowed;
}

.dropdown-item:hover:not(.disabled) {
    background-color: #f0f0f0;
}






/* TOGGLE LOGIN SWITCH */

 .toggle-container {
            position: relative;
            background-color: grey;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: inset 0 2px 5px rgba(0, 0, 0, 0.4);
            margin: auto;
            margin-bottom: 10px;
        }

  @media screen and (min-width: 769px) {

   .toggle-container {
            width: 350px;
            height: 45px;
            }
      }

    @media screen and (max-width: 769px) {

   .toggle-container {
            width: 100%;
            height: 45px;
            }

      }

  .toggle-container input[type="radio"] {
            display: none;
        }

        .toggle-button {
            position: absolute;
            top: 0;
            height: 100%;
            line-height: 45px; /* Center text vertically */
            color: white;
            opacity: 0.8;
            transition: width 0.5s ease-in-out, opacity 0.3s ease-in-out;
            z-index: 1;
            font-size: 15px;
            cursor: pointer; /* Change cursor to pointer */
            font-family: 'Mulish',sans-serif;
        }

        .toggle-button.password {
            left: 0%;
            width: 85%; /* Initial width */
            text-align: center;
        }
        .toggle-button.code {
            right: 0%;
            width: 15%; /* Initial width */
            text-align: center;
        }

        .toggle-container .login-slider {
            position: absolute;
            background-color: green;
            border-radius: 25px;
            width: 85%;
            height: 100%;
            transition: all 0.5s ease-in-out;
            z-index: 0;
            box-shadow: 0 2px 2px rgba(0, 0, 0, 0.2),
                        inset 0 -2px 5px rgba(0, 0, 0, 0.3);
                        left: 0%;
        }


        #password:checked ~ .login-slider {
            left: 0%;
        }
        #code:checked ~ .login-slider {
            left: 15%;
        }

        #password:checked ~ .toggle-button.password {
            opacity: 0  ;
            width: 85%; /* Reduced width when selected */
        }
        #password:checked ~ .toggle-button.code {
            opacity: 0.8;
            width: 15%; /* Expanded width when the other option is selected */
        }
        #code:checked ~ .toggle-button.code {
            opacity: 1;
            width: 85%; /* Reduced width when selected */
        }
        #code:checked ~ .toggle-button.password {
            opacity: 0.8;
            width: 15%; /* Expanded width when the other option is selected */
        }

        /* New Button Styles */
        .login-button-75, .code-button-75 {
            position: absolute;
            top: 0;
            height: 100%;
            border: none;
            border-radius: 25px;
            color: white;
            font-size: 15px;
            line-height: 45px;
            text-align: center;
            box-shadow: 0 5px 6px rgba(0, 0, 0, 0.4), inset 0 -2px 1px rgba(0, 0, 0, 0.4);
            transition: opacity 0.5s ease-in-out;
            z-index: 1;
            cursor: pointer;
            font-size: 19px;
            width: 85%;
        }

        .login-button-75 {
            left: 0%;
            background-color: var(--login-green);
        }

        .login-button-75:hover {
        border: 3px var(--login-green-highlight) solid;
        line-height: 35px;
            background-color: green;
        }

        .code-button-75 {
            right: 0%;
            background-color: var(--login-green);
        }

        .code-button-75:hover {
        border: 3px var(--login-green-highlight) solid;
        line-height: 35px;
        }

        .hidden {
            display: none;
        }

/*CODE*/



.code-box {
    text-align: center;
    font-family: 'Arvo', serif;

    pointer-events: none; /* Prevent typing initially */
    cursor: not-allowed;
    opacity: 0.3;
    }

.code-box.enabled {
    pointer-events: auto; /* Enable typing */
    background-color: #ffffff; /* Reset background when enabled */
    cursor: auto;
}

@media screen and (min-width: 769px) {
    .code-box {
        font-size: 2.1em;
        max-width: 3.2em;
    }
}

@media screen and (max-width: 769px) {
    .code-box {
        text-align: center;
        font-family: 'Arvo', serif;
        font-size: 1.7em;
        max-width: 2.5em;
    }
}

form input:focus {
/*     border: 2px solid #160E21; */
    box-shadow: 0px 0px 8px 4px #78F4F4;
}



#password-form, #code-form {
    transition: opacity 0.3s ease;
    opacity: 0;
    display: none; /* Initially hidden */
}

#password-form {
    display: block; /* Assuming password form is shown initially */
    opacity: 1;
}

@keyframes shake {
  0%, 100% { transform: translateX(0); }
  10%, 30%, 50%, 70%, 90% { transform: translateX(-10px); }
  20%, 40%, 60%, 80% { transform: translateX(10px); }
}

.shake {
  animation: shake 0.4s linear;
}

.form-item {
max-width: 500px;
margin-right: auto;
  margin-left: auto;
}


.form-container {
max-width: 800px !important;
box-shadow: #0000001f 0px 5px 20px;
}


.reset-title {
font-size: 2em;
  text-align: center;
  font-family: 'Arvo';
  margin-top: 30px;
}

/* #sub-status-message { */
/*   margin-top: 0px; */
/*   margin-bottom: 10px; */
/*   font-family: 'Arvo', Georgia, serif; */
/*   color: var(--subdued-text); */
/*   font-weight: 300; */
/* } */

</style>


<?php require_once ("../header-2026b.php");?>



