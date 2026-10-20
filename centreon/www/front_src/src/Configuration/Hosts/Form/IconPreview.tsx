import {
  Image,
  ImageVariant,
  type InputPropsWithoutGroup,
  LoadingSkeleton
} from '@centreon/ui';

import { type FormikValues, useFormikContext } from 'formik';
import type { ReactElement } from 'react';

import type { Icon } from '../models';

const previewSize = 25;

// The picked image beside its picker, as the host group form shows it.
const IconPreview = ({ dataTestId }: InputPropsWithoutGroup): ReactElement => {
  const { values } = useFormikContext<FormikValues>();

  const icon = values.extendedInfos?.icon as Icon | null | undefined;

  return (
    <div
      className="flex size-10 items-center justify-center"
      data-testid={dataTestId}
    >
      {icon?.url && (
        <Image
          // `useLoadImage` caches by `alt`, so it must identify the image.
          alt={icon.name}
          fallback={<LoadingSkeleton />}
          height={previewSize}
          imagePath={icon.url}
          variant={ImageVariant.Contain}
          width={previewSize}
        />
      )}
    </div>
  );
};

// Each option shows its image before its name.
export const renderIconOption = (option: { name: string }): ReactElement => {
  const { name, url } = option as Icon;

  return (
    <div className="flex items-center gap-2">
      <Image
        alt={name}
        className="size-4 shrink-0"
        fallback={<LoadingSkeleton className="size-4" />}
        imagePath={url}
      />
      {name}
    </div>
  );
};

export default IconPreview;
