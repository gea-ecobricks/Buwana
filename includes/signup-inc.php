
<!--  Set any page specific graphics to preload-->

<!--  Set any page specific graphics to preload
<link rel="preload" as="image" href="../webps/ecobrick-team-blank.webp" media="(max-width: 699px)">
<link rel="preload" as="image" href="../svgs/richard-and-team-day.svg">
<link rel="preload" as="image" href="../svgs/richard-and-team-night.svg">
<link rel="preload" as="image" href="../webps/biosphere2.webp">
<link rel="preload" as="image" href="../webps/biosphere-day.webp">-->



<?php require_once ("../meta/$page-$lang.php");?>

<STYLE>


/* ----------  Earthling picker  ---------- */

#emoji-section { margin-top: 20px; }

.emoji-tabs {
    display: flex;
    list-style: none;
    margin: 0 0 12px 0;
    padding: 0;
    border-bottom: 2px solid var(--subdued-text);
    overflow-x: auto;              /* mobile friendliness */
}
.emoji-tabs li {
    cursor: pointer;
    padding: 8px 14px;
    white-space: nowrap;
    font-size: 0.95rem;
    border-bottom: 3px solid transparent;
    transition: background .15s, border-color .15s;
}
.emoji-tabs li:hover      { background:var(--form-background); }
.emoji-tabs li.active     { border-color:#2d8cff; font-weight:600; }

.emoji-grid {
    display: none;                /* only the active grid is shown */
    flex-wrap: wrap;
    gap: 8px;
}
.emoji-grid.active { display: flex; }

.emoji-option {
    font-size: 28px;
    padding: 6px 10px;
    border: 2px solid transparent;
    border-radius: 8px;
    cursor: pointer;
    transition: border-color .15s, transform .1s;
}
.emoji-option:hover        { border-color:#bbb; }
.emoji-option.selected     { border-color:#2d8cff; transform: scale(1.05); }

.emoji-hint { margin-top: 6px; font-size: .9em; color:#555; }











/* .earthcycles-logo { */
/*   margin:15px auto 0 auto; */
/*   padding:5px 5px 5px 5px; */
/*   width: 200px; */
/*   height: 200px; */
/*   border: none; */
/* } */



/* .module-btn { */
/*   background: var(--emblem-green); */
/*   width: 100%; */
/*   display: flex; */
/* } */

/* .module-btn:hover { */
/*   background: var(--emblem-green-over); */
/* } */




/* Confirm email */


  .code-boxes {
        display: flex;
        justify-content: center;
        gap: 10px;
    }
    .code-box {
        text-align: center;
        font-family: 'Arvo', serif;
        font-size: 2em;
        max-width: 3em;
    }
    #second-code-confirm {
        display: none;
    }


/*     .hidden { */
/*         display: none; */
/*     } */
/*     .error { */
/*         color: red; */
/*     } */
/*     .success { */
/*         color: green; */
/*     } */



</STYLE>





<?php require_once ("../header-2026b.php");?>



