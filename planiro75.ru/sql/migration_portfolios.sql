-- Портфели проектов: иерархия портфелей и связь с проектами (many-to-many)

CREATE TABLE IF NOT EXISTS project_portfolios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  parent_portfolio_id INT UNSIGNED NULL,
  name VARCHAR(255) NOT NULL,
  description TEXT NULL,
  color CHAR(7) NOT NULL DEFAULT '#0ea5e9',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_pf_org (organization_id),
  KEY idx_pf_parent (parent_portfolio_id),
  CONSTRAINT fk_pf_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_pf_parent FOREIGN KEY (parent_portfolio_id) REFERENCES project_portfolios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portfolio_projects (
  portfolio_id INT UNSIGNED NOT NULL,
  project_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (portfolio_id, project_id),
  KEY idx_pp_project (project_id),
  CONSTRAINT fk_pp_portfolio FOREIGN KEY (portfolio_id) REFERENCES project_portfolios(id) ON DELETE CASCADE,
  CONSTRAINT fk_pp_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

