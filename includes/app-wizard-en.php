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

  .wizard-step { display:none; }
  .wizard-step.active { display:block; }
  .wizard-buttons { text-align:center; margin-top:20px; }
  .wizard-buttons button { margin:0 5px; }

  .kick-ass-submit { text-decoration:none; font-family: 'Arvo', serif; }
  .simple-button {
    display: inline-block;
    padding: 8px 16px;
    background: var(--button-2-2);
    color: white;
    border-radius: 6px;
    text-decoration: none;
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
