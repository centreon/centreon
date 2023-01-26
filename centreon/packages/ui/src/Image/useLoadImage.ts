import { useAtom } from 'jotai';
import { equals, isEmpty, isNil, prop } from 'ramda';

import { imagesAtom } from './atoms';

interface Resource {
  read: () => string;
}

enum Status {
  error = 'error',
  pending = 'pending',
  success = 'success'
}

const createImage = (imageSrc: string): Resource => {
  let status = Status.pending;
  let image;

  const imageLoaderPromise = new Promise<string>((resolve, reject) => {
    const img = new Image();
    img.src = imageSrc;
    img.onload = (): void => resolve(imageSrc);
    img.onerror = (): void =>
      reject(new Error(`Failed to load image ${imageSrc}`));
  })
    .then((result: string): void => {
      status = Status.success;
      image = result;
    })
    .catch((e: Error) => {
      status = Status.error;
      image = e;
    });

  return {
    read: (): string => {
      if (equals(Status.pending, status)) {
        throw imageLoaderPromise;
      }

      if (equals(Status.error, status)) {
        return '';
      }

      return image;
    }
  };
};

export const useLoadImage = ({ imageSrc, alt }): Resource => {
  const [images, setImages] = useAtom(imagesAtom);

  const image = prop(alt, images);

  if (!isNil(image) || isEmpty(image)) {
    return {
      read: () => ''
    };
  }

  const newImage = createImage(imageSrc);

  setImages((currentImages) => ({ ...currentImages, [alt]: newImage }));

  return newImage;
};
