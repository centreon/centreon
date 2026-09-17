import {
	fireEvent,
	type RenderResult,
	render,
	screen,
	waitFor,
} from "../../test/testRenderer";
import Wizard from ".";

const renderWizardThreeSteps = (): RenderResult =>
	render(
		<Wizard
			open
			steps={[
				{
					Component: (): JSX.Element => <div>Step 1</div>,
					skipFormChangeCheck: true,
					stepName: "step label 1",
				},
				{
					Component: (): JSX.Element => <div>Step 2</div>,
					skipFormChangeCheck: true,
					stepName: "step label 2",
				},
				{
					Component: (): JSX.Element => <div>Step 3</div>,
					skipFormChangeCheck: true,
					stepName: "step label 3",
				},
			]}
		/>,
	);

const renderWizardOneStep = (): RenderResult =>
	render(
		<Wizard
			open
			steps={[
				{
					Component: (): JSX.Element => <div>Step 1</div>,
					skipFormChangeCheck: true,
					stepName: "step label 1",
				},
			]}
		/>,
	);


describe(Wizard, () => {
	it("displays the step labels", () => {
		renderWizardThreeSteps();

		expect(screen.getByText("step label 1")).toBeInTheDocument();
		expect(screen.getByText("step label 2")).toBeInTheDocument();
		expect(screen.getByText("step label 3")).toBeInTheDocument();
	});

	it("does not display the step labels when there is only one step", () => {
		renderWizardOneStep();

		expect(screen.queryByText("step label 1")).not.toBeInTheDocument();
	});

	it("navigates between steps", async () => {
		renderWizardThreeSteps();

		fireEvent.click(screen.getByText("Next"));

		await waitFor(() => {
			expect(screen.getByText("Step 2")).toBeInTheDocument();
		});

		fireEvent.click(screen.getByText("Previous"));

		await waitFor(() => {
			expect(screen.getByText("Step 1")).toBeInTheDocument();
		});
	});
});
