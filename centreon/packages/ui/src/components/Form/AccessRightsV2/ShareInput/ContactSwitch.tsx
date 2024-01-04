import { useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { FormControlLabel, Radio, RadioGroup } from '@mui/material';

import { ContactType, Labels } from '../models';
import { contactTypeAtom } from '../atoms';
import { Subtitle } from '../../../..';

interface Props {
  labels: Labels['add'];
}

const ContactSwitch = ({ labels }: Props): JSX.Element => {
  const { t } = useTranslation();

  const setContactType = useSetAtom(contactTypeAtom);

  const change = (event: React.ChangeEvent<HTMLInputElement>): void => {
    setContactType(event.target.value as ContactType);
  };

  return (
    <>
      <Subtitle>{t(labels.title)}</Subtitle>
      <RadioGroup row defaultValue={ContactType.Contact} onChange={change}>
        <FormControlLabel
          control={<Radio />}
          label={labels.contact}
          value={ContactType.Contact}
        />
        <FormControlLabel
          control={<Radio />}
          label={labels.contactGroup}
          value={ContactType.ContactGroup}
        />
      </RadioGroup>
    </>
  );
};

export default ContactSwitch;
