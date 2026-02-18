

<!--  Set any page specific graphics to preload-->
<link rel="preload" as="image" href="../svgs/b-logo.svg">

<?php require_once ("../meta/$page-$lang.php");?>

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

@media (min-width: 769px) {
  .app-grid {
    grid-template-columns: 1fr 1fr;
  }
}

.app-display-box {
  position: relative;
  border: 1px solid var(--subdued-text);
  background-color: var(--lighter);
  border-radius: 12px;
  padding: 15px;
  text-align: center;
  transition: all 0.3s ease;
  cursor: pointer;
  box-shadow: 0 1px 5px rgba(0,0,0,0.06);
  text-decoration: none !important;
  overflow: hidden;
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
  transition: opacity 0.3s ease;
}

.app-actions {
  display: none;
  justify-content: center;
  gap: 8px;
  margin-top: 45px;
  flex-direction: column;
  align-items: center;
}

.app-display-box:hover .app-actions,
.app-display-box.active .app-actions {
  display: flex;
}

.app-display-box:hover .app-slogan,
.app-display-box.active .app-slogan {
  opacity: 0;
}

.simple-button {
  display: inline-block;
  padding: 8px 16px;
  background: var(--button-2-2);
  color: white;
  border-radius: 6px;
  text-decoration: none;
}

.button-row {
  display: flex;
  gap: 8px;
}

.about-link {
  font-size: 0.9em;
  color: var(--subdued-text);
  text-decoration: none;
}

.about-link:hover {
  color: var(--text-color);
  text-decoration: underline;
}

.app-actions {
  position: absolute;
  top: 50%;
  left: 0;
  right: 0;
  display: flex;
  justify-content: center;
  gap: 8px;
  flex-direction: column;
  align-items: center;
  transform: translateY(-50%);
  opacity: 0;
  transition: opacity 0.3s ease;
}

.app-display-box:hover .app-actions {
  opacity: 1;
}

.app-display-box:hover h4,
.app-display-box:hover p {
  opacity: 0;
}

.simple-button {
  display: inline-block;
  padding: 8px 16px;
  background: var(--button-2-2);
  color: white;
  border-radius: 6px;
  text-decoration: none;
}

.buwana-lead-banner {
    height:350px;
    background:no-repeat center;
    background-size:contain;
    }

/* Horizontal slider for the landing page */
#content-slider {
    overflow: hidden;
    width: 100%;
    position: relative;
}

#content-slider .slides-wrapper {
    display: flex;
    transition: transform 0.5s ease-in-out;
}

#content-slider .slide {
    min-width: 100%;
    box-sizing: border-box;
}


@media (max-width: 769px) {
.buwana-lead-banner {
    width:100%;
    height:225px;
    margin-bottom: -35px;
    }
}

@media (min-width: 769px) {
.buwana-lead-banner {
    width:100%;
    height:350px;
    }
}

</style>

<?php require_once ("../header-2025.php");?>