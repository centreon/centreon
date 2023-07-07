import NamesList from '../NamesList';

export const projectLeaders = ['Julien Mathis', 'Romain Le Merlus'];

const ProjectLeaders = (): JSX.Element => {
  return <NamesList names={projectLeaders} />;
};

export default ProjectLeaders;
