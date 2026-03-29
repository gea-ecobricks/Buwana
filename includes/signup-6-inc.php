
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

@media (max-width: 768px) {
  input[list] {
    font-size: 18px;
    padding: 12px 10px 12px 50px;
    -webkit-appearance: none; /* Safari fix */
  }

  .select-wrapper {
    padding: 0;
    margin: 0;
  }

  .select-icon {
    left: 12px;
    font-size: 18px;
  }
}

</STYLE>


<?php require_once ("../header-2026b.php");?>



