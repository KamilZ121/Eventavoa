const API = "/eventavoa/backend/logic/authHandler.php";

function loginTarget() {
    const target = new URLSearchParams(window.location.search).get("redirect");
    return target && target.startsWith("/eventavoa/") && !target.startsWith("//")
        ? target
        : "/eventavoa/index.html";
}

function showTab(tab) {
    $("#loginForm").toggleClass("d-none", tab !== "login");
    $("#registerForm").toggleClass("d-none", tab !== "register");

    $("#login-tab").toggleClass("active", tab === "login");
    $("#register-tab").toggleClass("active", tab === "register");

    hideMessage();
}

function showMessage(text, success) {
    $("#message")
        .removeClass("d-none alert-success alert-danger")
        .addClass(success ? "alert-success" : "alert-danger")
        .text(text);
}

function hideMessage() {
    $("#message")
        .addClass("d-none")
        .removeClass("alert-success alert-danger")
        .text("");
}

function login() {
    $.ajax({
        type: "POST",
        url: API,
        dataType: "json",
        data: {
            method: "login",
            benutzername: $("#login_user").val(),
            passwort: $("#login_pass").val(),
            remember: $("#remember").is(":checked") ? "1" : "0"
        },
        success: function(response) {
            if (response.success) {
                window.location.href = loginTarget();
            } else {
                showMessage(response.message, false);
            }
        },
        error: function(xhr) {
            showMessage(xhr.responseJSON?.message || "Login konnte nicht durchgeführt werden.", false);
        }
    });
}

function register() {
    const passwort = $("#reg_pass").val();
    const passwort2 = $("#reg_pass2").val();
    const paymentType = $("#reg_payment_type").val();

    if (passwort !== passwort2) {
        showMessage("Passwörter stimmen nicht überein.", false);
        return;
    }

    if (paymentType === "") {
        showMessage("Bitte Zahlungsart auswählen.", false);
        return;
    }

    $.ajax({
        type: "POST",
        url: API,
        dataType: "json",
        data: {
            method: "register",
            anrede: $("#reg_anrede").val(),
            vorname: $("#reg_vorname").val(),
            nachname: $("#reg_nachname").val(),
            adresse: $("#reg_adresse").val(),
            plz: $("#reg_plz").val(),
            ort: $("#reg_ort").val(),
            email: $("#reg_email").val(),
            benutzername: $("#reg_benutzername").val(),
            passwort: passwort,
            passwort2: passwort2,
            payment_type: paymentType,
            payment_owner: `${$("#reg_vorname").val()} ${$("#reg_nachname").val()}`,
            payment_identifier: $("#reg_payment_identifier").val()
        },
        success: function(response) {
            if (response.success) {
                showMessage("Registrierung erfolgreich. Bitte einloggen.", true);
                showTab("login");
            } else {
                showMessage(response.message, false);
            }
        },
        error: function(xhr) {
            showMessage(xhr.responseJSON?.message || "Registrierung konnte nicht durchgeführt werden.", false);
        }
    });
}
