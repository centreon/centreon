import NamesList from '../NamesList';

export const developers = [
  'Adrien Gelibert',
  'Adrien Morais',
  'Allyriane Launois',
  'Arnaud Buathier',
  "Bruno D'auria",
  'David Boucher',
  'Dmytro Iosypenko',
  'Jérémy Delpierre',
  'Jérémy Jaouen',
  'Kevin Duret',
  'Laurent Calvet',
  'Laurent Pinsivy',
  'Loïc Thomas',
  'Maximilien Bersoult',
  'Quentin Garnier',
  'Rémi Grès',
  'Tamaz Cheishvili',
  'Tom Darneix',
  'Thomas Arnaud',
  'Walid Termellil'
];

const Developers = (): JSX.Element => {
  return <NamesList names={developers} />;
};

export default Developers;
