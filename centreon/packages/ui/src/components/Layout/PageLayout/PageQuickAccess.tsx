import { useTranslation } from 'react-i18next';

import AddIcon from '@mui/icons-material/Add';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';

import { Button, Menu } from '../..';

interface NamedEntity {
  id: number | string;
  name: string;
}

type Props = {
  create: () => void;
  elements: Array<NamedEntity>;
  goBack: () => void;
  isActive: (id: number | string) => boolean;
  labels: {
    create: string;
    goBack: string;
  };
  navigateToElement: (id: number | string) => () => void;
};

export const PageQuickAccess = ({
  elements,
  isActive,
  navigateToElement,
  goBack,
  create,
  labels
}: Props): JSX.Element => {
  const { t } = useTranslation();

  return (
    <Menu>
      <Menu.Button data-testid="quickaccess" />
      <Menu.Items>
        {elements?.map((element) => (
          <Menu.Item
            key={`${element.id}`}
            onClick={navigateToElement(element.id)}
            {...(isActive(element.id) && {
              isActive: true,
              isDisabled: true
            })}
          >
            {element.name}
          </Menu.Item>
        ))}
        <Menu.Divider key="divider" />
        <Menu.Item key="create">
          <>
            <Button
              icon={<ArrowBackIcon />}
              iconVariant="start"
              variant="ghost"
              onClick={goBack}
            >
              {t(labels.goBack)}
            </Button>
            <Button
              icon={<AddIcon />}
              iconVariant="start"
              variant="secondary"
              onClick={create}
            >
              {t(labels.create)}
            </Button>
          </>
        </Menu.Item>
      </Menu.Items>
    </Menu>
  );
};
