import { type RefObject, useEffect } from "react";

import ResizeObserver from "resize-observer-polyfill";

interface Props<TRef> {
	onResize: ResizeObserverCallback;
	ref: RefObject<TRef | undefined>;
}

const useResizeObserver = <TRef extends Element>({
	ref,
	onResize,
}: Props<TRef>): void => {
	useEffect(() => {
		const ro = new ResizeObserver(onResize);

		const element = ref?.current as Element;

		ro.observe(element);

		return (): void => {
			ro.unobserve(element);
		};
	}, [onResize, ref?.current]);
};

export default useResizeObserver;
