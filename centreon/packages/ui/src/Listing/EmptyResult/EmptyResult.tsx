import { EmptyRow } from '../Row/EmptyRow';

interface EmptyResultProps {
  label: string;
}

const EmptyResult = ({ label }: EmptyResultProps): JSX.Element => (
  <EmptyRow>{label}</EmptyRow>
);

export { EmptyResult };
