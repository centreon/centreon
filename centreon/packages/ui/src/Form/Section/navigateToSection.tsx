export const useNavigateToSection = () => {
    return (sectionName: string) => {
        const section = document.querySelector(
            `[data-section-group-form-id="${sectionName}"]`
        );

        section?.scrollIntoView({ behavior: 'smooth' });
    };
};
