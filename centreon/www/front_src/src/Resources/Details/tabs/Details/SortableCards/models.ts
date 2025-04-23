import { DetailCardLine } from '../DetailsCard/cards';

export interface CardsLayout extends DetailCardLine {
  id: string;
  width: number;
}

export enum ExpandAction {
  add = 0,
  remove = 1
}

export interface ChangeExpandedCardsProps {
  action: ExpandAction;
  card: string;
}
