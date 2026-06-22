const ORDER_URL = "/eventavoa/backend/logic/orderHandler.php";

$(document).ready(function () {
    $("#header").load("/eventavoa/frontend/sites/header.html");
    $("#footer").load("/eventavoa/frontend/sites/footer.html");
    const orderId = new URLSearchParams(window.location.search).get("order");
    $.ajax({type: "GET", url: ORDER_URL, data: {action: "getInvoice", order_id: orderId}, dataType: "json"})
        .done(showInvoice)
        .fail(function () { $("#invoice").html('<div class="alert alert-danger">Rechnung nicht verfügbar.</div>'); });
});

function showInvoice(response) {
    const order = response.order;
    const customer = response.kunde || {};
    const address = response.adresse || {};
    let rows = "";
    response.items.forEach(function (item) {
        rows += `<tr><td>${escapeHtml(item.name)}</td><td class="text-end">${item.menge}</td><td class="text-end">${item.einzelpreis.toFixed(2)} €</td><td class="text-end">${(item.menge * item.einzelpreis).toFixed(2)} €</td></tr>`;
    });
    $("#invoice").html(`
        <header class="d-flex justify-content-between"><h1 class="text-primary">Eventavoa</h1><div class="text-end"><h2 class="h4">Rechnung</h2><div>${escapeHtml(order.rechnungsnummer)}</div><div>${formatDate(order.created_at)}</div></div></header>
        <hr><p><strong>Rechnungsadresse:</strong><br>${escapeHtml(customer.anrede)} ${escapeHtml(customer.vorname)} ${escapeHtml(customer.nachname)}<br>${escapeHtml(address.strasse)}<br>${escapeHtml(address.plz)} ${escapeHtml(address.ort)}</p>
        <table class="table"><thead><tr><th>Produkt</th><th class="text-end">Menge</th><th class="text-end">Einzelpreis</th><th class="text-end">Summe</th></tr></thead><tbody>${rows}</tbody><tfoot><tr><th colspan="3" class="text-end">Gesamt</th><th class="text-end">${order.gesamt.toFixed(2)} €</th></tr></tfoot></table>
        <button id="printInvoice" class="btn btn-primary d-print-none">Drucken</button>`);
    $("#printInvoice").click(function () { window.print(); });
}

function formatDate(value) { return value.substring(0, 10).split("-").reverse().join(". "); }
function escapeHtml(value) { return $("<div>").text(value == null ? "" : String(value)).html(); }
