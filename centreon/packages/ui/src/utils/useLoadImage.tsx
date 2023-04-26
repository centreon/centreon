import { useLayoutEffect } from 'react';

import { useSetAtom } from 'jotai';

const loadImage = (imagePath: string): Promise<string> =>
  new Promise((resolve, reject) => {
    const image = new Image();

    image.src = imagePath;
    image.onload = (): void => resolve(imagePath);
    image.onerror = reject;
  });

export const useLoadImage = ({ imagePath, atom }): void => {
  const setImage = useSetAtom(atom);

  useLayoutEffect(() => {
    loadImage(imagePath)
      .then((image) => {
        setImage(image);
      })
      .catch(() => {
        setImage(null);
      });
  }, [imagePath]);
};
