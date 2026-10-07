import AddIcon from '@mui/icons-material/Add';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import { CircularProgress } from '@mui/material';

import { useTranslation } from 'react-i18next';

import { Button, Menu } from '../..';

interface NamedEntity {
  id: number | string;
  name: string;
}

type Props = {
  create?: () => void;
  elements: Array<NamedEntity>;
  goBack: () => void;
  isActive: (id: number | string) => boolean;
  isDisabled?: (id: number | string) => boolean;
  isLoading?: boolean;
  labels: {
    create: string;
    goBack: string;
  };
  // Observed at the bottom of the list to load the next page when it is reached
  loadMoreRef?: (node: Element | null) => void;
  navigateToElement: (id: number | string) => () => void;
};

export const PageQuickAccess = ({
  elements,
  isActive,
  isDisabled,
  isLoading,
  loadMoreRef,
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
        <div
          className="max-h-[50vh] overflow-y-auto"
          data-testid="quickaccess-elements"
        >
          {elements?.map((element) => (
            <Menu.Item
              isActive={isActive(element.id)}
              isDisabled={isDisabled?.(element.id)}
              key={`${element.id}`}
              onClick={navigateToElement(element.id)}
            >
              {element.name}
            </Menu.Item>
          ))}
          {loadMoreRef && <div className="h-px" ref={loadMoreRef} />}
          {isLoading && (
            <div className="flex justify-center py-2">
              <CircularProgress size={20} />
            </div>
          )}
        </div>
        <Menu.Divider key="divider" />
        <div className="px-2 pb-2 flex gap-4">
          <Button
            icon={<ArrowBackIcon />}
            iconVariant="start"
            onClick={goBack}
            variant="ghost"
          >
            {t(labels.goBack)}
          </Button>
          {create && (
            <Button
              icon={<AddIcon />}
              iconVariant="start"
              onClick={create}
              variant="secondary"
            >
              {t(labels.create)}
            </Button>
          )}
        </div>
      </Menu.Items>
    </Menu>
  );
};
