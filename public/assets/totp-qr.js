(function () {
  function renderQr(target) {
    var text = target.getAttribute('data-qr-text') || '';
    if (!text || typeof qrcode !== 'function') {
      target.textContent = 'QR-Code konnte nicht erzeugt werden.';
      return;
    }

    var qr = qrcode(0, 'M');
    qr.addData(text);
    qr.make();
    target.innerHTML = qr.createSvgTag({
      cellSize: 5,
      margin: 3,
      scalable: true
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-qr-text]').forEach(renderQr);
  });
}());
