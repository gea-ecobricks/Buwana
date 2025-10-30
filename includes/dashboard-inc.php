

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

.monthly-change-positive {
  color: var(--emblem-green);
}

.monthly-change-negative {
  color: red;
}

.kick-ass-submit {
  text-decoration: none;
  font-family: 'Arvo', serif;
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

#boat-management-panel {
  margin-top: 20px;
}

#boat-management-panel .panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 12px;
}

#boat-management-panel h3 {
  margin: 0;
  font-size: 1.2em;
  color: var(--h1);
}

#boat-management-panel .panel-description {
  margin: 0 0 15px 0;
  color: var(--subdued-text);
  font-size: 0.95em;
}

#boat-management-panel .boat-state-box {
  padding: 16px;
  border-radius: 10px;
  font-size: 0.95em;
  transition: background-color 0.3s ease, border-color 0.3s ease, color 0.3s ease;
}

#boat-management-panel .boat-state-box.is-empty {
  border: 1px dashed var(--subdued-text);
  background: var(--lighter);
  color: var(--subdued-text);
  text-align: center;
}

#boat-management-panel .boat-state-box.has-boat {
  border: 1px solid var(--button-2-2);
  background: var(--form-field-background);
  color: var(--text-color);
}

.boat-modal-container {
  max-width: 520px;
  margin: auto;
  text-align: left;
}

.boat-modal-container h2 {
  margin: 0 0 8px 0;
  color: var(--h1);
}

.boat-modal-container p {
  margin-top: 0;
  color: var(--subdued-text);
}

.boat-modal-form {
  display: grid;
  gap: 14px;
}

.boat-modal-form .form-row {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.boat-modal-form .form-field {
  flex: 1 1 210px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.boat-modal-form label {
  font-size: 0.9em;
  font-weight: 600;
  color: var(--subdued-text);
}

.boat-modal-form input,
.boat-modal-form select,
.boat-modal-form textarea {
  padding: 10px;
  border-radius: 8px;
  border: 1px solid var(--subdued-text);
  background: var(--form-field-background);
  color: var(--text-color);
  font-family: 'Mulish', sans-serif;
}

.boat-modal-form textarea {
  min-height: 110px;
  resize: vertical;
}

.boat-modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 8px;
}

.boat-modal-actions button {
  padding: 10px 18px;
  border-radius: 8px;
  border: none;
  cursor: pointer;
  font-family: 'Mulish', sans-serif;
  font-size: 0.95em;
}

.boat-modal-actions .cancel-button {
  background: var(--lighter);
  color: var(--text-color);
}

.boat-modal-actions .submit-button {
  background: var(--button-2-2);
  color: #fff;
}

.boat-modal-actions button:hover {
  opacity: 0.9;
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