import { useTranslation } from 'react-i18next';

interface Props {
  children: React.ReactNode;
  label: string;
}

const SectionRow = ({ label, children }: Props): JSX.Element => {
  const { t } = useTranslation();

  return (
    <div className="grid grid-cols-1 gap-2 sm:grid-cols-[180px_1fr] sm:gap-7">
      <h2 className="text-base font-medium text-primary-main">{t(label)}</h2>
      <div className="text-sm text-text-secondary">{children}</div>
    </div>
  );
};

export default SectionRow;
