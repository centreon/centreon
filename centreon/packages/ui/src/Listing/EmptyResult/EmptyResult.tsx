import { EmptyRow } from '../Row/EmptyRow';

interface EmptyResultProps {
  label: string | JSX.Element;
}

const EmptyResult = ({ label }: EmptyResultProps): JSX.Element => (
  <EmptyRow>{label}</EmptyRow>
);

export { EmptyResult };
