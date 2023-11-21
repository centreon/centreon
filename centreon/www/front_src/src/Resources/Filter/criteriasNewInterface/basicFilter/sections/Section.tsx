import { useStyles } from './sections.style';

interface Section {
  inputGroup: JSX.Element;
  selectInput: JSX.Element;
  status: JSX.Element;
}

const Section = ({ status, inputGroup, selectInput }: Section): JSX.Element => {
  const { classes } = useStyles();

  return (
    <div className={classes.basicInputs}>
      {selectInput}
      {status}
      {inputGroup}
    </div>
  );
};

export default Section;
