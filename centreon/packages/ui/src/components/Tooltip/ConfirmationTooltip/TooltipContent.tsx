import { ActionsList } from '../../..';
import { ActionVariants } from '../../../ActionsList/models';

import { useStyles } from './ConfirmationTooltip.styles';

interface Props {
  actions: Array<{
    action: () => void;
    label: string;
    secondaryLabel?: string;
    variant?: ActionVariants;
  }>;
}

const TooltipContent = ({ actions }: Props): JSX.Element => {
  const { classes } = useStyles();

  return (
    <ActionsList
      actions={actions.map(({ label, action, variant, secondaryLabel }) => ({
        label,
        onClick: action,
        secondaryLabel,
        variant
      }))}
      className={classes.list}
    />
  );
};

export default TooltipContent;
