<?php
	abstract class Component
	{
		private $_events=array();
		private $_components=array();
		private $_componentsMeta=array();
		private $_initialized=false;
		private $_disposed=false;
		
		/* [MAGIC FUNCTIONS] */
		public function __call($function, $args)
		{
			if ($this->hasComponent($function))
				return $this->getComponent($function);
		}
		
		public function __get($name)
		{
			$getter = 'get' . ucfirst($name);
			if (method_exists($this, $getter))
				$this->$getter();
		}
		
		public function __set($name, $value)
		{
			$setter = 'set' . ucfirst($name);
			if (method_exists($this, $setter))
				$this->$setter($value);
		}
		
		/* [STATE HANDLING] */
		public function configure($data)
		{
			// Avoid security issues by reserving all private property
			// names before doing the mass variable assignment from the config
			$reserved = array();
			$o = new ReflectionObject($this);
			foreach($o->getProperties(ReflectionProperty::IS_PRIVATE) as $var)
				$reserved[] = $var->name;
				
			if (is_array($data))
			{
				foreach($data as $prop=>$value)
				{
					if (is_string($prop) && array_search($prop, $reserved) === false)
						$this->$prop = $value;
				}
			}
		}
		
		public function init()
		{
			$this->_initialized = true;
		}
		
		public function dispose()
		{
			$this->_disposed = true;
		}
		
		public function isInitialized()
		{
			return $this->_initialized;
		}	
		
		public function isDisposed()
		{
			return $this->_disposed;
		}
		/* [/STATE HANDLING] */
		
		/* [EVENT HANDLING] */
		public function attachEvent($name)
		{
			if (!$this->hasEvent($name))
				$this->_events[$name] = new Event($name);
			return $this->_events[$name];
		}
		
		public function detachEvent($name)
		{
			if ($this->hasEvent($name))
				unset($this->_events[$name]);
		}
		
		public function hasEvent($name)
		{
			return isset($this->_events[$name]);
		}
		
		public function addEventHandler($event, $handler)
		{
			if ($this->hasEvent($event))
				$this->_events[$event]->addHandler($handler);
		}	
		
		public function removeEventHandler($event, $handler)
		{
			if ($this->hasEvent($event))
				$this->_events[$event]->removeHandler($handler);
		}
		
		public function raiseEvent($event, EventArgs $e)
		{
			if ($this->hasEvent($event))
				$this->_events[$event]->invoke($this, $e);
		}
		
		protected function registerEvents($names)
		{
			foreach($names as $name)
				$this->attachEvent($name);
		}
		/* [/EVENT HANDLING] */
		
		/* [COMPONENT HANDLING] */
		public function getComponent($id, $createIfNotExists=true)
		{
			if (isset($this->_components[$id]))
				return $this->_components[$id];
			elseif (isset($this->_componentsMeta[$id]) && $createIfNotExists)
			{
				throw new Exception("not implemented");
				/*
				$meta = $this->_componentsMeta[$id];
				unset($this->_componentsMeta[$id]);
				
				$c = WireFrame::createComponent($meta);
				$c->init();
				return ($this->_components[$id] = $c);
				*/
			}
		}
		
		public function setComponent($id, $value)
		{
			if (isset($this->_components[$id]))
			{
				if ($value === null)
					unset($this->_components[$id]);
				else
					$this->_components[$id] = $value;
			}
			else
			{
				$this->_components[$id] = $value;
			}

			if ($value !== null && !$value->isInitialized())
				$value->init();
		}
		
		public function hasComponent($name, $loadedOnly=false)
		{
			return $loadedOnly 
				? isset($this->_components[$name])
				: isset($this->_components[$name]) || isset($this->_componentsMeta[$name]);
		}
		
		public function configureComponent($name, $metadata)
		{
			if (isset($this->_components[$name])) // already constructed, call configure
				$this->_components[$name]->configure($metadata);
			elseif (isset($this->_componentsMeta[$name]))
				$this->_componentsMeta[$name] = array_merge($this->_componentsMeta[$name], $metadata);
			else
				$this->_componentsMeta[$name] = $metadata;
		}
		
		
		// Register metadata for components. This will delay construction of
		// the objects until a call to getComponent with $createIfNotExists=true 
		// is called for the component.
		public function registerComponents($componentsMetadata)
		{
			foreach($componentsMetadata as $id=>$meta)
			{
				if (is_array($meta))
				{	
					if (!isset($meta['class']))
						$meta['class'] = $id;
					$this->_componentsMeta[$id] = $meta;
				}
			}
		}
		/* [/COMPONENT HANDLING] */
	}
?>