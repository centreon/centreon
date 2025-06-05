import { Tooltip as TooltipComponent } from '@centreon/ui/components';
import InfoOutlinedIcon from '@mui/icons-material/InfoOutlined';
import { useTranslation } from 'react-i18next';
import { WidgetPropertyProps } from '../../../models';

const Tooltip = ({
  secondaryLabel,
  propertyName
}: Pick<WidgetPropertyProps, 'secondaryLabel' | 'propertyName'>) => {
  const { t } = useTranslation();

  return (
    <>
      {secondaryLabel && (
        <TooltipComponent
          followCursor={false}
          label={t(secondaryLabel)}
          position="right"
        >
          <InfoOutlinedIcon
            color="primary"
            data-testid={`secondary-label-${propertyName}`}
            fontSize="small"
          />
        </TooltipComponent>
      )}
    </>
  );
};

export default Tooltip;
