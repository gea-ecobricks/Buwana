

<!--  Set any page specific graphics to preload-->
<link rel="preload" as="image" href="../svgs/b-logo.svg">

<?php require_once ("../meta/buwana-index-en.php");?>

<style>



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

.top-wrapper {
  display: flex;
  justify-content: space-between;
  align-items: center;
  height: auto;
  margin-bottom: 20px;
  padding: 15px;
  background: #ffffff0d;
  border-radius: 10px;
  line-height: 1.5;
}

.login-status {
  font-family: 'Mulish', Arial, Helvetica, sans-serif;
  font-size: 1em;
  color: grey;
}

.admin-status {
  text-align: right;
}

.client-id {
  font-family: 'Mulish', Arial, Helvetica, sans-serif;
  font-size: 1em;
  color: var(--text-color);
}

.page-name {
  font-family: 'Mulish', Arial, Helvetica, sans-serif;
  font-size: 1.6em;
  color: var(--h1);
}

.chart-container {
  width: 100%;
  margin: 0 auto;
  position: relative;
}

.chart-controls {
  position: absolute;
  bottom: 10px;
  right: 10px;
}

.dataTables_wrapper {
  margin: 0 auto;
}

.dashboard-module {
  background: var(--form-field-background);
  padding: 20px;
  border-radius: 10px;
}

.chart-caption {
  text-align: center;
  font-family: 'Mulish', Arial, Helvetica, sans-serif;
  font-size: 1em;
  color: var(--subdued-text);
  margin-top: 6px;
  margin-bottom: 20px;
}

.app-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 20px;
  margin: 0 auto 30px auto;
  max-width: 800px;
  padding: 10px;
}

@media (min-width: 500px) {
  .app-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (min-width: 800px) {
  .app-grid {
    grid-template-columns: repeat(3, 1fr);
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
  display: flex;
  flex-direction: column;
  align-items: center;
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
  margin: 8px 0 0 0;
}

.kick-ass-submit {
  text-decoration: none;
}

.simple-button {
  display: inline-block;
  padding: 8px 16px;
  background: var(--button-2-2);
  color: white;
  border-radius: 6px;
  text-decoration: none;
}

.edit-button-row {
  display: flex;
  justify-content: center;
  gap: 10px;
  flex-wrap: wrap;
}

  .breadcrumb {
  text-align: right;
  font-family: 'Mulish', Arial, Helvetica, sans-serif;
  font-size: 1em;
  color: var(--subdued-text);
  margin-top: 20px;
}

  .breadcrumb a {
  color: var(--subdued-text);
  text-decoration: none;
  transition: color 0.2s;
}

  .breadcrumb a:hover {
  color: var(--h1);
  text-decoration: underline;
}

@media (max-width: 768px) {
  .top-wrapper .page-name,
  .top-wrapper .client-id {
    display: none;
  }
}

</style>

<?php require_once ("../header-2025.php");?>