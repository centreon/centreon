import { includes } from 'ramda';
import { useEffect } from 'react';

interface Props {
  onClose: () => void;
  onNext?: () => void;
  onPrevious?: () => void;
}

const isTyping = (target: EventTarget | null): boolean =>
  target instanceof HTMLElement &&
  (target.isContentEditable ||
    includes(target.tagName, ['INPUT', 'SELECT', 'TEXTAREA']));

// Goes through the logs of the listing without leaving the keyboard, as in a
// mail client: ↑/k and ↓/j for the previous and next log, Escape to close
export const useKeyboardNavigation = ({
  onClose,
  onNext,
  onPrevious
}: Props): void => {
  useEffect(() => {
    const handleKeyDown = (event: KeyboardEvent): void => {
      if (
        event.altKey ||
        event.ctrlKey ||
        event.metaKey ||
        isTyping(event.target)
      ) {
        return;
      }

      const handlers: Record<string, (() => void) | undefined> = {
        ArrowDown: onNext,
        ArrowUp: onPrevious,
        Escape: onClose,
        j: onNext,
        k: onPrevious
      };

      const handler = handlers[event.key];

      if (!handler) {
        return;
      }

      event.preventDefault();
      handler();
    };

    window.addEventListener('keydown', handleKeyDown);

    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [onClose, onNext, onPrevious]);
};
