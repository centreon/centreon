import { Button } from '@centreon/ui/components';
import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import { useAtomValue } from 'jotai';
import { isGeneratedAtom } from '../atoms';

import {
  labelCancel,
  labelExportConfiguration
} from '../../../translatedLabels';

interface Props {
  close: () => void;
}

const Buttons = ({ close }: Props): ReactElement => {
  const { t } = useTranslation();
  const isCommandGenerated = useAtomValue(isGeneratedAtom);

  return (
    <div className="flex justify-end gap-2">
      <Button onClick={close} size="medium" variant="ghost">
        {t(labelCancel)}
      </Button>
      <Button
        data-testid="generate-command"
        disabled={!isCommandGenerated}
        onClick={() => undefined}
        size="medium"
      >
        {t(labelExportConfiguration)}
      </Button>
    </div>
  );
};

export default Buttons;
