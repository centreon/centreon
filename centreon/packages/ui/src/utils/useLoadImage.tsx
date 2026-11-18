import { type PrimitiveAtom, useSetAtom } from 'jotai';
import { useLayoutEffect } from 'react';

const loadImage = (imagePath: string): Promise<string> =>
  new Promise((resolve, reject) => {
    const image = new Image();

    image.src = imagePath;
    image.onload = (): void => resolve(imagePath);
    image.onerror = reject;
  });

interface UseLoadImageProps {
  atom: PrimitiveAtom<string | null>;
  imagePath: string;
}

export const useLoadImage = ({ imagePath, atom }: UseLoadImageProps): void => {
  const setImage = useSetAtom(atom);

  useLayoutEffect(() => {
    loadImage(imagePath)
      .then((image) => {
        setImage(image);
      })
      .catch(() => {
        setImage(null);
      });
  }, [imagePath, setImage]);
};
