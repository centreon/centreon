import { Suspense } from 'react';

import { Atom, useAtomValue } from 'jotai';
import { atomWithPending } from 'jotai-suspense';
import { isEmpty, isNil } from 'ramda';

import { Skeleton } from '@mui/material';

import { useLoadImage } from '../utils/useLoadImage';

interface Props {
  alt: string;
  atom: Atom<string>;
  className?: string;
  fallback?: JSX.Element;
  height?: number | string;
  imagePath: string;
  width?: number | string;
}

export const createImageAtom = (): Atom<string> => atomWithPending<string>();

const ImageContent = ({
  width,
  height,
  alt,
  atom,
  className,
  fallback,
}: Omit<Props, 'imagePath'>): JSX.Element | null => {
  const image = useAtomValue(atom);

  if (isNil(image) || isEmpty(image)) {
    return fallback || null;
  }

  return (
    <img
      alt={alt}
      className={className}
      src={image as string}
      style={{ height, objectFit: 'cover', width }}
    />
  );
};

export const Image = ({
  imagePath,
  width,
  height,
  alt,
  fallback = (
    <Skeleton
      animation="wave"
      height={height}
      variant="rectangular"
      width={width}
    />
  ),
  atom,
  className = '',
}: Props): JSX.Element => {
  useLoadImage({ atom, imagePath });

  if (isNil(imagePath) || isEmpty(imagePath)) {
    return fallback;
  }

  return (
    <Suspense fallback={fallback}>
      <ImageContent
        alt={alt}
        atom={atom}
        className={className}
        fallback={fallback}
        height={height}
        width={width}
      />
    </Suspense>
  );
};
