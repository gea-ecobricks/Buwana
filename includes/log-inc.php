

<?php require_once ("../meta/log-$lang.php");?>


<STYLE>

    #main {
        height: fit-content !important;
        padding-bottom: 100px;
    }


/* Suggestions box container */
#community-suggestions {
    background-color: var(--main-background); /* White background for suggestions */
    width: calc(100% - 30px); /* Make the suggestions box the same width as the input */
    max-height: 150px; /* Limit the height */
    overflow-y: auto; /* Allow scrolling if suggestions exceed max height */
    z-index: 1000; /* Ensure the dropdown is above other elements */
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); /* Subtle shadow for the dropdown */
    margin-top: -3px;
}

/* Individual suggestion items */
.suggestion-item {
    padding: 10px;
    cursor: pointer;
    font-size: 1em;
    color: var(--text-color);
    background-color: var(--background-main); /* White background for individual items */
}

.suggestion-item:hover {
    background-color: var(--button-2-1); /* Light gray background on hover */
}

#watershed-suggestions {
    background-color: var(--main-background); /* White background for suggestions */
    width: calc(100% - 30px); /* Make the suggestions box the same width as the input */
    max-height: 150px; /* Limit the height */
    overflow-y: auto; /* Allow scrolling if suggestions exceed max height */
    z-index: 1000; /* Ensure the dropdown is above other elements */
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); /* Subtle shadow for the dropdown */
    margin-top: -3px;
}


.dropdown {
  float: right;
  overflow: hidden;
  margin-bottom: -10px;
}

#splash-bar {
  background-color: var(--top-header);
  filter: none !important;
  margin-bottom: -200px !important;
}
.photo-upload-container {
    width: 100%;
    padding: 10px;
    background-color: var(--lighter);
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 5px; /* Adds space between elements */
    margin-bottom: 30px;
}

.custom-file-upload {
    display: inline-block;
    padding: 10px 20px;
    font-size: 1.3rem;
    color: var(--text-color);
    background-color: grey;
    border: 2px solid transparent;
    border-radius: 6px;
    cursor: pointer;
    text-align: center;
    transition: background-color 0.3s, border-color 0.3s;
}

.custom-file-upload:hover {
    background-color: var(--accordion-background); /* Lighten the grey background on hover */
    border-color: var(--text-color); /* Changes border color on hover */
}

.custom-file-upload input[type="file"] {
    display: none; /* Hide the actual file input */
}

.file-name {
    margin-top: 8px;
    font-size: 1rem;
    color: var(--text-color);
}

.form-caption {
    font-size: 1rem;
    color: var(--text-color);
/*     text-align: center; */
}



#upload-progress-button {
color: white;
  padding: 10px 20px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  background-color: #12b712;
  background-size: 0% 100%;
  transition: background-size 0.5s ease;
  font-size: 1.3em;
  width: 100%;
  margin-top: 30px;
  }


/* Specify the progress bar color */
input[type="submit"].progress-bar {
  background: url(../svgs/square-upload-progress.svg) left center repeat-y, gray; /* Combined background */
  background-size: auto; /* Auto size for image background */
}



  .login-one-banner {
    z-index:20;
    position: absolute;
    text-align: center;
    width: 100%;
    height:140px;
    margin-top:20px;
  }

</style>

<?php require_once ("../header-2026b.php");?>

