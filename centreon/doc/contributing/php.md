# How to Contribute – PHP Endpoints

Thank you for contributing to our Open Source project! Here are the steps to follow when proposing PHP endpoints.

## 1. How to Choose the Target Branch

- New features: `develop`
- Security fixes / Bugfixes: the most recent version where the bug is present

We will handle the necessary backports internally.

Examples:
- Creating a new endpoint → `develop`
- Discovering a bug in 24.10.x → `dev-24.10.x`

## 2. Branch Naming Convention

- Use only hyphens (`-`) to separate words.
- Do not use slashes (`/`) in the branch name.
- Examples:
  ```
  feature-add-user-endpoint
  fix-authentication-bug
  ```

## 3. Running Coding Style Tools and Tests

Before submitting a Pull Request, please make sure your code follows the standards and that all tests pass:

- **Check and fix coding style on all code**:
  ```sh
  composer cs      # Check coding style
  composer cs:fix  # Automatically fix coding style
  ```

- **Check and fix coding style only on modified files**:
  ```sh
  composer cs:diff      # Check coding style on modified files
  composer cs:diff:fix  # Fix coding style on modified files
  ```

- **Check and fix code quality with Rector**:
  ```sh
  composer rector      # Check code quality (Rector)
  composer rector:fix  # Automatically fix with Rector
  ```

- **Check architecture compliance with Deptrac**:
  ```sh
  composer dep:new      # Check architecture integrity (Deptrac)
  ```

- **Run static analysis (PHPStan)**:
  ```sh
  composer stan
  ```

- **Run unit tests**:
  ```sh
  composer test
  ```

- **Run PHPUnit tests (new architecture src/App)**:
  ```sh
  composer test:new
  ```

## 4. Respect the `src/Core` Architecture

The `src/Core` directory follows a hexagonal architecture (Domain-Driven Design).  
Each feature/domain has its own directory tree, for example: `Contact`, `Host`, `ResourceAccess`, etc.

### Typical Domain Structure

Each domain generally contains the following layers:

- **Domain**:  
  Contains business entities, models, interfaces, exceptions, validation rules, etc.  
  Example:
  ```
  src/Core/Contact/Domain/Model/
  src/Core/Contact/Domain/AdminResolver.php
  ```

- **Application**:  
  Contains use cases, repository interfaces, application services, application exceptions, etc.  
  Example:
  ```
  src/Core/Contact/Application/UseCase/
  src/Core/Contact/Application/Repository/
  src/Core/Contact/Application/Exception/
  ```

- **Infrastructure**:  
  Contains concrete implementations: repositories, API controllers, configuration, voters, etc.  
  Example:
  ```
  src/Core/Contact/Infrastructure/Repository/
  src/Core/Contact/Infrastructure/API/
  src/Core/Contact/Infrastructure/Configuration/
  ```

### Best Practices

- **Add your code in the appropriate domain** (e.g., `Contact`, `Host`, etc.).
- **Follow the Domain / Application / Infrastructure separation**.
- **Create sub-directories as needed** (UseCase, Repository, Model…).
- **Name your classes and files explicitly**.
- **Follow existing patterns** to organize your endpoints, services, models, etc.

### Example: Adding a New Contact Endpoint

To add a Contact endpoint:

- Define models and business rules in `src/Core/Contact/Domain/`.
- Add the use case in `src/Core/Contact/Application/UseCase/`.
- Implement data access / API layer in `src/Core/Contact/Infrastructure/`.

## 5. API Documentation

### Where to Place the Documentation

- Place each OpenAPI file in the directory corresponding to the resource, for example:
  ```
  doc/API/latest/onPremise/Configuration/Host/
  doc/API/latest/onPremise/Configuration/ContactGroup/
  ```
- Use an explicit file name that references the resource and the use case, for example:  
  `AddAndFindHosts.yaml`

### Writing Guidelines

- **Follow the OpenAPI 3.x specification** (YAML).
- **Describe each endpoint**: method, path, parameters, request body, responses, error codes.
- **Document data schemas** in the `components/schemas` section.
- **Use included files** for your request/response schemas.
- **Add examples** for requests and responses.
- **Use English** for all titles, descriptions, and examples.
- **Maintain a clear and consistent structure** with the other files in the directory.

### Minimal OpenAPI File Example

```yaml
openapi: 3.0.3
info:
  title: Host API
  version: 1.0.0
paths:
  /hosts:
    get:
      summary: List hosts
      responses:
        '200':
          description: List of hosts
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/HostList'
components:
  schemas:
    HostList:
      type: array
      items:
        $ref: '#/components/schemas/Host'
    Host:
      type: object
      properties:
        id:
          type: integer
        name:
          type: string
```

### Checklist Before Submitting

- [ ] The file is placed in the correct directory.
- [ ] Endpoints, schemas, and examples are documented.
- [ ] The OpenAPI syntax is valid (use a validator if needed). [Swagger Editor](https://editor.swagger.io)
- [ ] The documentation is written in English.

## 6. When Opening the PR

- Take into account feedback from Copilot/CodeRabbit.
- The CI will not run automatically and must be triggered manually by an internal team member. This will also add an `in-backlog` label and create a ticket for our teams.

---

Following this organization ensures code consistency and maintainability.  
If in doubt, take inspiration from existing code or ask the maintainers for guidance.
