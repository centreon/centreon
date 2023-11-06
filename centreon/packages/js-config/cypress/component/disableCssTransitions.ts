const disableMotion = (win): void => {
  const injectedStyleEl = win.document.getElementById('__cy_disable_motion__');
  if (injectedStyleEl) {
    return;
  }
  win.document.head.insertAdjacentHTML(
    'beforeend',
    `
      <style id="__cy_disable_motion__">
        /* Disable CSS transitions. */
        *, *::before, *::after { -webkit-transition: none !important; -moz-transition: none !important; -o-transition: none !important; -ms-transition: none !important; transition: none !important; }
        /* Disable CSS animations. */
        *, *::before, *::after { -webkit-animation: none !important; -moz-animation: none !important; -o-animation: none !important; -ms-animation: none !important; animation: none !important; }
      </style>
    `.trim()
  );
};

export default disableMotion;
