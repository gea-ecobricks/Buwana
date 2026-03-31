
<?php require_once ("../meta/$page-$lang.php");?>

<STYLE>



.form-container {
  padding-top: 30px !important;
}

.app-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 20px;
  margin: 0 auto 30px auto;
  max-width: 600px;
  padding: 10px;
}

@media (min-width: 600px) {
  .app-grid {
    grid-template-columns: 1fr 1fr;
  }
}

.app-display-box {
  border: 1px solid var(--subdued-text);
  background-color: var(--lighter);
  border-radius: 12px;
  padding: 15px;
  text-align: center;
  transition: all 0.3s ease;
  cursor: pointer;
  box-shadow: 0 1px 4px rgba(0,0,0,0.05);
}

.app-display-box:hover {
  transform: translateY(-3px);
  box-shadow: 0 4px 8px rgba(0,0,0,0.1);
  background-color: var(--light);
}

.app-display-box img {
  width: 80px;
  height: 80px;
  object-fit: contain;
  margin-bottom: 10px;
}

.app-display-box h4 {
  margin: 5px 0 8px 0;
  font-size: 1.1em;
  color: var(--text-color);
}

.app-display-box p {
  font-size: 0.9em;
  color: var(--subdued-text);
  margin: 0;
}




</STYLE>


<?php require_once ("../header-2026b.php");?>



