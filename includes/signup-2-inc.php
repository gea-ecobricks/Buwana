

<?php require_once ("../meta/$page-$lang.php");?>

<STYLE>

#main {
    height: fit-content;
}

.form-container {
    max-width: 800px;
    margin: 0 auto;
    box-shadow: #0000001f 0px 5px 20px;
}

.bullet-container {
  position: relative;
  padding-left: 28px; /* Leave space for the bullet */
  margin-bottom: 20px;
}

.bullet-indicator {
  position: absolute;
  top: 37px;
  left: 20px;
  width: 12px;
  height: 12px;
  background-color: grey;
  border-radius: 50%;
  transition: background-color 0.3s ease;
}

/* Optional: style toggle icons */
.toggle-password {
  cursor: pointer;
  position: absolute;
  right: 10px;
  top: 50%;
  font-size: 20px;
  transform: translateY(-50%);
}


#last-name-field {
    position: absolute;
    left: -9999px;
    top: auto;
    width: 1px;
    height: 1px;

    z-index: 0;
}


    .hidden {
        display: none;
    }
    .error {
        color: red;
    }
    .success {
        color: limegreen;
    }




.spinner {
    display: none;
    position: absolute;
    top: 28px;  /* Center vertically in the input field */
    left: 11px; /* Distance from the right edge of the input field */
    transform: translateY(-50%); /* Ensures the spinner is exactly centered vertically */
    width: 20px;
    height: 20px;
    border: 4px solid rgba(0,0,0,0.1);
    border-top: 4px solid var(--emblem-pink);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

.pin-icon {
    display: block;
    position: absolute;
    top: 30%;  /* Center vertically in the input field */
    left: 16px; /* Distance from the right edge of the input field */
    transform: translateY(-50%); /* Ensures the spinner is exactly centered vertically */
    width: 15px;
    height: 0px;

}

.spinner.green {
    background-color: limegreen;
    border: 1px solid limegreen;
        width: 12px;
        height: 12px;
        margin-top: 12px !important;
}

.spinner.red {
    background-color: red;
    border: 1px solid red;
            width: 12px;
            height: 12px;
              margin-top: 12px !important;
}

@keyframes spin {
    0% { transform: rotate(0deg); translateY(-50%); }
    100% { transform: rotate(360deg); translateY(-50%); }
}



.bullet-indicator.green {
  background-color: limegreen !important;
}


</STYLE>





<?php require_once ("../header-2025.php");?>



