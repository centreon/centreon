import { ReactElement, useCallback, useEffect, useRef } from 'react';

import { atom, useAtom } from 'jotai';

import { useResizeObserver } from '../../../utils/useResizeObserver';
import { Tooltip, TooltipProps } from '../Tooltip';

import { useStyles } from './TextOverflowTooltip.styles';

type TextOverflowTooltipProps = Omit<TooltipProps, 'followCursor' | 'hasCaret'>;

/** @description
 * This component is used to display a tooltip when the text is too long, and displays the full text on hover.
 *
 * It's up to the child component to define it's `width` / `max-width`, and ellipsis.
 *
 * For single lines use :
 * ```
 *  width: var(--width-of-the-text);
 *  white-space: nowrap;
 * ```
 *
 * For multi-lines use :
 * ```
 *  width: var(--width-of-the-text);
 *  display: -webkit-box;
 *  -webkit-box-orient: vertical;
 *  -webkit-line-clamp: var(--max-lines-of-the-text);
 * ```
 */

const TextOverflowTooltip = ({
  children,
  label,
  position = 'bottom',
  isOpen,
  ...tooltipProps
}: TextOverflowTooltipProps): ReactElement => {
  const { classes } = useStyles();

  const stateAtom = useRef(
    atom<{
      hasOverflow: boolean;
      isOpen: boolean;
    }>({ hasOverflow: false, isOpen: false })
  ).current;

  const [state, setState] = useAtom(stateAtom);

  const onMouseEnter = useCallback(
    () =>
      state.hasOverflow &&
      setState({
        ...state,
        isOpen: true
      }),
    [state.hasOverflow]
  );

  const onMouseLeave = useCallback(
    () =>
      state.hasOverflow &&
      setState({
        ...state,
        isOpen: false
      }),
    [state.hasOverflow]
  );

  const elRef = useRef<HTMLDivElement>(null);

  const onResize = (): void => {
    const { firstElementChild: el } = elRef.current || {};
    if (el instanceof HTMLElement) {
      setState({
        ...state,
        hasOverflow:
          el.scrollWidth > el.offsetWidth || el.scrollHeight > el.offsetHeight
      });
    }
  };

  useResizeObserver({
    onResize,
    ref: elRef
  });

  useEffect(() => {
    if (isOpen) setState({ ...state, isOpen });
    onResize();
  }, [isOpen, label]);

  return (
    <Tooltip
      followCursor
      hasCaret={false}
      isOpen={state.isOpen}
      label={label}
      position={position}
      {...tooltipProps}
    >
      <div
        className={classes.textOverflowTooltip}
        data-has-overflow={state.hasOverflow}
        ref={elRef}
        onMouseEnter={onMouseEnter}
        onMouseLeave={onMouseLeave}
      >
        {children}
      </div>
    </Tooltip>
  );
};

export { TextOverflowTooltip };
