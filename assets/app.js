document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('canvas[data-qr]').forEach((canvas) => {
    const value = canvas.dataset.qr || '';
    if (window.QRCode && value) {
      QRCode.toCanvas(canvas, value, {
        width: 112,
        margin: 1,
        errorCorrectionLevel: 'M',
        color: { dark: '#111827', light: '#ffffff' }
      }, (error) => {
        if (error) {
          const note = document.createElement('span');
          note.className = 'qr-error';
          note.textContent = '二维码暂不可用';
          canvas.replaceWith(note);
        }
      });
    } else {
      const note = document.createElement('span');
      note.className = 'qr-error';
      note.textContent = '二维码库加载失败';
      canvas.replaceWith(note);
    }
  });

  document.querySelectorAll('[data-copy]').forEach((button) => {
    button.addEventListener('click', async () => {
      const text = button.dataset.copy || '';
      try {
        await navigator.clipboard.writeText(text);
        const old = button.textContent;
        button.textContent = '已复制';
        setTimeout(() => { button.textContent = old; }, 1400);
      } catch {
        window.prompt('复制以下代理地址：', text);
      }
    });
  });
});
