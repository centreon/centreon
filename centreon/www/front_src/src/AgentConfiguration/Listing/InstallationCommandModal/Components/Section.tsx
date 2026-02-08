import { Typography } from '@mui/material';
import { ReactElement } from 'react';

interface Props {
  title: string;
  order: number;
}

const SubTitle = ({ title, order }: Props): ReactElement => {
  return (
    <div className="flex items-center gap-1.5 mb-1">
      <div className="w-6 h-6 bg-text-primary text-primary-contrastText text-center rounded-xl">
        {order}
      </div>
      <Typography variant="subtitle1" className="font-medium">
        {title}
      </Typography>
    </div>
  );
};

export const Section = ({
  children,
  order,
  title
}: { children; order: number; title: string }) => {
  return (
    <div>
      <SubTitle order={order} title={title} />
      <div className="pl-7.5">{children}</div>
    </div>
  );
};
