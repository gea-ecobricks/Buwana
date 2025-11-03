<?php require_once ("../meta/$page-$lang.php");?>

<style>
    #main {
        height: fit-content;
        padding-bottom: 80px;
    }

    #form-submission-box {
        height: fit-content;
    }

    #status-message {
        font-size: 1.8em;
        font-weight: 600;
    }

    #sub-status-message {
        margin-top: 10px;
        font-size: 1em;
        color: var(--text-2);
    }

    .support-details {
        background: var(--lighter);
        border-radius: 12px;
        padding: 24px;
        margin-top: 30px;
    }

    .support-details h3 {
        margin-top: 0;
        margin-bottom: 20px;
        font-size: 1.2em;
    }

    .support-details dl {
        display: grid;
        grid-template-columns: minmax(160px, 1fr) minmax(0, 2fr);
        column-gap: 18px;
        row-gap: 12px;
        margin: 0;
    }

    .support-details dt {
        font-weight: 600;
        margin: 0;
        color: var(--text-color);
    }

    .support-details dd {
        margin: 0;
        color: var(--text-color);
        word-break: break-word;
    }

    .support-details .info-row {
        display: contents;
    }

    .support-placeholder {
        margin-top: 30px;
        padding: 18px;
        border-radius: 12px;
        background: var(--lighter);
        text-align: center;
        font-size: 1.1em;
    }

    @media screen and (max-width: 700px) {
        .support-details dl {
            grid-template-columns: 1fr;
        }

        .support-details dt {
            padding-bottom: 4px;
        }
    }
</style>

<?php require_once ("../header-2025.php");?>
