import { Button } from '@centreon/ui/components';

import { useAtomValue } from 'jotai';
import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import {
  labelCancel,
  labelExportConfiguration
} from '../../../../translatedLabels';
import { isGeneratedAtom } from '../../atoms';

interface Props {
  close: () => void;
}

const Buttons = ({ close }: Props): ReactElement => {
  const { t } = useTranslation();
  const isCommandGenerated = useAtomValue(isGeneratedAtom);

  const onClick = (): void => {
    close();
  };

  return (
    <div className="flex justify-end gap-2">
      <Button onClick={close} size="medium" variant="ghost">
        {t(labelCancel)}
      </Button>
      <Button
        data-testid="generate-command"
        disabled={!isCommandGenerated}
        onClick={onClick}
        size="medium"
      >
        {t(labelExportConfiguration)}
      </Button>
    </div>
  );
};

export default Buttons;
