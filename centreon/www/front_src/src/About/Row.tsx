import type { ReactElement, ReactNode } from 'react';
import { useTranslation } from 'react-i18next';

interface Props {
  children: ReactNode;
  label: string;
  withTopDivider?: boolean;
}

const Row = ({
  label,
  children,
  withTopDivider = true
}: Props): ReactElement => {
  const { t } = useTranslation();

  return (
    <div
      className={`grid grid-cols-1 items-baseline gap-x-7 gap-y-1 py-6 md:grid-cols-[188px_1fr] ${
        withTopDivider ? 'border-t border-divider' : ''
      }`}
    >
      <p className="text-base leading-tight font-medium text-section-title">
        {t(label)}
      </p>
      <div>{children}</div>
    </div>
  );
};

export default Row;
