

<!--  Set any page specific graphics to preload-->
<link rel="preload" as="image" href="../svgs/b-logo.svg">

<?php require_once ("../meta/buwana-index-en.php");?>

<style>

        .buwana-lead-banner {
        margin-top:-70px;
        margin-bottom:20px;
        }

 #buwana-top-logo {
 background: url('../svgs/b-logo.svg') center no-repeat;

    background-size: contain;
     background-repeat: no-repeat;
     background-position: center;
     height: 80%;
     display: flex;
     cursor: pointer;
     width: 100%;
     margin-right: 70px;
     margin-top: 5px;
  }

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
  box-shadow: 0 1px 5px rgba(0,0,0,0.06);
  text-decoration: none !important;
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
  color: var(--text);
}

.app-display-box p {
  font-size: 0.9em;
  color: var(--subdued-text);
  margin: 0;
}

</style>

<?php require_once ("../header-2025.php");?>