import { CSSProperties } from 'react';

interface Props {
  contentClientRect: {
    height: number;
    width: number;
  } | null;
}

export const applyTranformStylesForZoom = ({
  contentClientRect
}: Props): CSSProperties => {
  const contentRect = {
    height: contentClientRect?.height || 1,
    width: contentClientRect?.width || 1
  };
  const isPortrait = contentRect.height > contentRect.width;
  const sizes = isPortrait ? ['width', 'height'] : ['height', 'width'];
  const sizeScale = contentRect[sizes[0]] / contentRect[sizes[1]];

  const lengthToUse = isPortrait
    ? contentRect[sizes[1]] - contentRect[sizes[0]]
    : contentRect[sizes[0]];

  const t = sizeScale > 0.85 && isPortrait ? sizeScale * 4 : sizeScale / 2;
  const xScaleFactor = sizeScale > 0.7 && !isPortrait ? 10 : 6;

  return {
    transform: `translate(-${isPortrait ? 0 : contentRect.width * (sizeScale / xScaleFactor)}px, -${lengthToUse * (isPortrait ? t + 0.08 : t / 2)}px)`
  };
};

// DO NOT REMOVE: As the component is in work in progress, please this code in case we need
// const getAdditionalPadding = (): number => {
//   if (additionalScale > 0.05) {
//     return 0;
//   }

//   const padding =
//     additionalScale > 0.012
//       ? (1 / additionalScale) * (1 / zoom.transformMatrix.scaleY)
//       : 1 / additionalScale / zoom.transformMatrix.scaleY;

//   if (additionalScale < 0.009) {
//     const tweakScale = scaleLinear({
//       clamp: true,
//       domain: [0.009, 0.002],
//       range: [1, 8]
//     });

//     return padding - padding / tweakScale(additionalScale);
//   }

//   return padding;
// };
