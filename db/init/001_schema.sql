CREATE TABLE IF NOT EXISTS controllers (
  id SERIAL PRIMARY KEY,
  name TEXT NOT NULL,
  discription TEXT
);

CREATE TABLE IF NOT EXISTS controller_data (
  id BIGSERIAL PRIMARY KEY,
  pin TEXT NOT NULL,
  value DOUBLE PRECISION NOT NULL,
  controller_id INTEGER NOT NULL REFERENCES controllers(id) ON DELETE CASCADE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

INSERT INTO controllers (id, name, discription)
VALUES (1, 'arduino-uno-main', 'Arduino Uno web client controller')
ON CONFLICT (id) DO NOTHING;
