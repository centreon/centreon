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

global.IntersectionObserver = class IntersectionObserver {
  observe() {
    this.a = '';
    return null;
  }

  disconnect() {
    this.a = '';
    return null;
  }
};
