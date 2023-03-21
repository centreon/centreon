import { FC, Suspense, memo } from 'react';

import { makeStyles } from 'tss-react/mui';
import { equals, isNil } from 'ramda';

import { useLoadImage } from './useLoadImage';

export enum ImageVariant {
  Contain = 'contain',
  Cover = 'cover'
}

interface Props {
  alt: string;
  className?: string;
  fallback: JSX.Element;
  height?: number | string;
  imagePath: string;
  variant?: ImageVariant;
  width?: number | string;
}

const useStyles = makeStyles<Pick<Props, 'width' | 'height' | 'variant'>>()(
  (_, { width, height, variant }) => ({
    imageContent: {
      height,
      objectFit: variant,
      width
    }
  })
);

const ImageContent: FC<Omit<Props, 'fallback'>> = ({
  alt,
  className,
  height,
  width,
  imagePath,
  variant = ImageVariant.Cover
}) => {
  const { classes, cx } = useStyles({ height, variant, width });
  const image = useLoadImage({ alt, imageSrc: imagePath });
  image.read();

  return (
    <img
      alt={alt}
      className={cx(classes.imageContent, className)}
      src={imagePath}
    />
  );
};

const SuspendedImage = ({ fallback, ...props }: Props): JSX.Element | null => {
  if (isNil(props.imagePath)) {
    return null;
  }

  return (
    <Suspense fallback={fallback}>
      <ImageContent {...props} />
    </Suspense>
  );
};

export default memo(SuspendedImage, equals);
