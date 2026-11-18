SET NAMES utf8mb4;

-- Структурные подразделения по https://irozk.ru/sveden/struct
-- ВНИМАНИЕ: удаляет текущие проекты подразделений и связанные задачи (CASCADE).

DELETE FROM department_heads;
DELETE FROM user_departments;
UPDATE users SET department_id = NULL WHERE department_id IS NOT NULL;

DELETE t FROM tasks t
INNER JOIN projects p ON p.id = t.project_id;

DELETE FROM projects;
DELETE FROM departments;

INSERT INTO departments (organization_id, name, sort_order)
SELECT o.id, 'Ректорат', 1 FROM organizations o;
INSERT INTO departments (organization_id, name, sort_order)
SELECT o.id, 'Кадрово-юридический отдел', 2 FROM organizations o;
INSERT INTO departments (organization_id, name, sort_order)
SELECT o.id, 'Отдел материально-технического обеспечения', 3 FROM organizations o;
INSERT INTO departments (organization_id, name, sort_order)
SELECT o.id, 'Управление по сопровождению образовательной деятельности', 4 FROM organizations o;
INSERT INTO departments (organization_id, name, sort_order)
SELECT o.id, 'Центр информационно-издательской деятельности', 5 FROM organizations o;
INSERT INTO departments (organization_id, name, sort_order)
SELECT o.id, 'Кафедра педагогики, психологии и образовательного менеджмента', 6 FROM organizations o;
INSERT INTO departments (organization_id, name, sort_order)
SELECT o.id, 'Кафедра воспитания и дополнительного образования', 7 FROM organizations o;
INSERT INTO departments (organization_id, name, sort_order)
SELECT o.id, 'Кафедра общего образования', 8 FROM organizations o;
INSERT INTO departments (organization_id, name, sort_order)
SELECT o.id, 'Центр программно-технического обеспечения', 9 FROM organizations o;
INSERT INTO departments (organization_id, name, sort_order)
SELECT o.id, 'Центр инновационной деятельности', 10 FROM organizations o;

INSERT INTO projects (organization_id, work_year, department_id, name, description, color)
SELECT o.id, COALESCE(o.work_year, YEAR(CURRENT_DATE)), d.id, 'Рабочие проекты подразделения', 'Проекты и задачи структурного подразделения', '#2563eb'
FROM organizations o
INNER JOIN departments d ON d.organization_id = o.id AND d.sort_order = 1;
INSERT INTO projects (organization_id, work_year, department_id, name, description, color)
SELECT o.id, COALESCE(o.work_year, YEAR(CURRENT_DATE)), d.id, 'Рабочие проекты подразделения', 'Проекты и задачи структурного подразделения', '#0d9488'
FROM organizations o INNER JOIN departments d ON d.organization_id = o.id AND d.sort_order = 2;
INSERT INTO projects (organization_id, work_year, department_id, name, description, color)
SELECT o.id, COALESCE(o.work_year, YEAR(CURRENT_DATE)), d.id, 'Рабочие проекты подразделения', 'Проекты и задачи структурного подразделения', '#7c3aed'
FROM organizations o INNER JOIN departments d ON d.organization_id = o.id AND d.sort_order = 3;
INSERT INTO projects (organization_id, work_year, department_id, name, description, color)
SELECT o.id, COALESCE(o.work_year, YEAR(CURRENT_DATE)), d.id, 'Рабочие проекты подразделения', 'Проекты и задачи структурного подразделения', '#ea580c'
FROM organizations o INNER JOIN departments d ON d.organization_id = o.id AND d.sort_order = 4;
INSERT INTO projects (organization_id, work_year, department_id, name, description, color)
SELECT o.id, COALESCE(o.work_year, YEAR(CURRENT_DATE)), d.id, 'Рабочие проекты подразделения', 'Проекты и задачи структурного подразделения', '#db2777'
FROM organizations o INNER JOIN departments d ON d.organization_id = o.id AND d.sort_order = 5;
INSERT INTO projects (organization_id, work_year, department_id, name, description, color)
SELECT o.id, COALESCE(o.work_year, YEAR(CURRENT_DATE)), d.id, 'Рабочие проекты подразделения', 'Проекты и задачи структурного подразделения', '#0891b2'
FROM organizations o INNER JOIN departments d ON d.organization_id = o.id AND d.sort_order = 6;
INSERT INTO projects (organization_id, work_year, department_id, name, description, color)
SELECT o.id, COALESCE(o.work_year, YEAR(CURRENT_DATE)), d.id, 'Рабочие проекты подразделения', 'Проекты и задачи структурного подразделения', '#65a30d'
FROM organizations o INNER JOIN departments d ON d.organization_id = o.id AND d.sort_order = 7;
INSERT INTO projects (organization_id, work_year, department_id, name, description, color)
SELECT o.id, COALESCE(o.work_year, YEAR(CURRENT_DATE)), d.id, 'Рабочие проекты подразделения', 'Проекты и задачи структурного подразделения', '#ca8a04'
FROM organizations o INNER JOIN departments d ON d.organization_id = o.id AND d.sort_order = 8;
INSERT INTO projects (organization_id, work_year, department_id, name, description, color)
SELECT o.id, COALESCE(o.work_year, YEAR(CURRENT_DATE)), d.id, 'Рабочие проекты подразделения', 'Проекты и задачи структурного подразделения', '#9333ea'
FROM organizations o INNER JOIN departments d ON d.organization_id = o.id AND d.sort_order = 9;
INSERT INTO projects (organization_id, work_year, department_id, name, description, color)
SELECT o.id, COALESCE(o.work_year, YEAR(CURRENT_DATE)), d.id, 'Рабочие проекты подразделения', 'Проекты и задачи структурного подразделения', '#e11d48'
FROM organizations o INNER JOIN departments d ON d.organization_id = o.id AND d.sort_order = 10;
