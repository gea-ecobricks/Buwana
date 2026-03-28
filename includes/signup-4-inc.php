    <?php require_once ("../meta/$page-$lang.php");?>

    <STYLE>



    .hidden {
        display: none;
    }
    .error {
        color: red;
    }
    .success {
        color: green;
    }




.spinner {
    display: none;
    position: absolute;
    top: 25%;  /* Center vertically in the input field */
    left: 20px; /* Distance from the right edge of the input field */
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
    top: 35px;  /* Center vertically in the input field */
    left: 20px; /* Distance from the right edge of the input field */
    transform: translateY(-50%); /* Ensures the spinner is exactly centered vertically */
    width: 15px;
    height: 0px;

}

.spinner.green {
    background-color: green;
    border: 1px solid green;
}

.spinner.red {
    background-color: red;
    border: 1px solid red;
}

@keyframes spin {
    0% { transform: rotate(0deg); translateY(-50%); }
    100% { transform: rotate(360deg); translateY(-50%); }
}


/* .pin-icon { */
/* margin: -37px 0px 20px 12px; */
/* } */

/* ── Map preview wrapper ── */
#map-preview-wrapper {
    position: relative;
    margin-top: 4px;
}

#map {
    height: 40px;
    width: 100%;
    border-radius: 6px 6px 0 0;
    transition: height 0.35s ease;
}

#map.map-expanded {
    height: 350px;
}

#show-map-text {
    background: var(--button-2-1);
    padding: 8px 14px;
    cursor: pointer;
    border-radius: 0 0 6px 6px;
    user-select: none;
}

#show-map-text:hover {
    opacity: 0.88;
}

#show-map-text span {
    color: #ffffff;
    font-family: "Mulish", sans-serif;
    font-size: 0.88em;
}

#map-close-btn {
    position: absolute;
    top: 7px;
    right: 9px;
    background: rgba(0, 0, 0, 0.52);
    color: #ffffff;
    border: none;
    border-radius: 50%;
    width: 26px;
    height: 26px;
    font-size: 14px;
    line-height: 26px;
    text-align: center;
    cursor: pointer;
    display: none;
    z-index: 1000;
    padding: 0;
}

#map-close-btn:hover {
    background: rgba(0, 0, 0, 0.72);
}

    </STYLE>


    <?php require_once ("../header-2025.php");?>

