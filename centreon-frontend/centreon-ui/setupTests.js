import '@testing-library/jest-dom/extend-expect';
import registerRequireContextHook from 'babel-plugin-require-context-hook/register';

registerRequireContextHook();

document.createRange = () => ({
  setStart: () => {},
  setEnd: () => {},
  commonAncestorContainer: {
    nodeName: 'BODY',
    ownerDocument: document,
  },
});
