import { memo } from 'react';

import { equals, isNil } from 'ramda';
import { makeStyles } from 'tss-react/mui';

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
    fallbackContainer: {
      height: '100%',
      width: '100%'
    },
    imageContent: {
      height,
      objectFit: variant,
      width
    }
  })
);

const ImageContent = ({
  alt,
  className,
  height,
  width,
  imagePath,
  variant = ImageVariant.Cover,
  fallback
}: Props): JSX.Element => {
  const { classes, cx } = useStyles({ height, variant, width });
  const isImageLoaded = useLoadImage({ alt, imageSrc: imagePath });

  if (!isImageLoaded) {
    return (
      <div className={classes.fallbackContainer} data-testid={alt}>
        {fallback}
      </div>
    );
  }

  return (
    <img
      alt={alt}
      className={cx(classes.imageContent, className)}
      data-testid={alt}
      src={imagePath}
    />
  );
};

const SuspendedImage = ({ imagePath, ...props }: Props): JSX.Element | null => {
  if (isNil(imagePath)) {
    return null;
  }

  return <ImageContent {...props} imagePath={imagePath} />;
};

export default memo(SuspendedImage, equals);
