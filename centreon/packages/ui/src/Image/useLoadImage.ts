import { SetStateAction, useAtom } from 'jotai';
import { isEmpty, isNil, prop } from 'ramda';

import { imagesAtom } from './atoms';

interface CreateImageProps {
  alt: string;
  image?: string;
  imageSrc: string;
  setImages: (update: SetStateAction<Record<string, string>>) => void;
}

const createImage = ({
  setImages,
  imageSrc,
  image,
  alt
}: CreateImageProps): void => {
  const imageLoaderPromise = (): Promise<string> =>
    new Promise<string>((resolve, reject) => {
      const img = new Image();
      img.src = imageSrc;
      img.onload = (): void => resolve(imageSrc);
      img.onerror = (): void =>
        reject(new Error(`Failed to load image ${imageSrc}`));
    });

  if (!isNil(image) || isEmpty(image)) {
    return;
  }

  imageLoaderPromise()
    .then((result: string): void => {
      setImages((currentImages) => ({ ...currentImages, [alt]: result }));
    })
    .catch(() => {
      setImages((currentImages) => ({ ...currentImages, [alt]: '' }));
    });
};

export const useLoadImage = ({ imageSrc, alt }): boolean => {
  const [images, setImages] = useAtom(imagesAtom);

  const image = prop(alt, images);

  createImage({ alt, image, imageSrc, setImages });

  return !isNil(image) || isEmpty(image);
};
